<?php

namespace App\Actions\Marketplace;

use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Review;
use App\Models\User;
use App\Models\Visit;
use App\Policies\ClientPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\PhoneNumber;
use App\Support\StoredImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientAccess
{
    /**
     * @return array{url: string, whatsapp: string}
     */
    public function issue(Client $client): array
    {
        $actor = AgencyContext::user();

        if (! app(ClientPolicy::class)->view($actor, $client)) {
            throw new ApiException(ErrorCodes::CLIENT_FORBIDDEN, 403);
        }

        $client->join_token = Str::random(64);
        $client->save();

        return [
            'url' => config('wwork.frontend_url').'/join/'.$client->join_token,
            'whatsapp' => (string) $client->whatsapp,
        ];
    }

    /**
     * @return array{name: string, phone: string}
     */
    public function preview(string $token): array
    {
        $client = $this->client($token);

        return [
            'name' => $client->name,
            'phone' => PhoneNumber::digits($client->whatsapp),
        ];
    }

    /**
     * @param  array{name: string, password: string, email?: string|null}  $input
     */
    public function accept(string $token, array $input): User
    {
        $client = $this->client($token);
        $phone = PhoneNumber::digits($client->whatsapp);

        if ($phone === '') {
            throw new ApiException(ErrorCodes::CLIENT_JOIN_INVALID, 422);
        }

        $email = filled($input['email'] ?? null) ? (string) $input['email'] : null;
        $existing = User::query()->where('phone', $phone)->first();

        if ($existing !== null) {
            if (! Hash::check($input['password'], $existing->password)) {
                throw new ApiException(ErrorCodes::AUTH_FAILED, 401);
            }
            $user = $existing;
        } else {
            if ($email !== null && User::query()->where('email', $email)->exists()) {
                throw new ApiException(ErrorCodes::REGISTER_EMAIL_TAKEN, 422);
            }
            $user = User::query()->create([
                'name' => $input['name'],
                'email' => $email,
                'phone' => $phone,
                'password' => $input['password'],
                'locale' => Locale::En,
                'must_change_password' => false,
                'terms_accepted_at' => now(),
                'first_access_at' => now(),
            ]);
        }

        if ($email !== null) {
            $client->email = $email;
        }
        $client->name = $input['name'];
        $client->user_id = $user->id;
        $client->join_token = null;
        $client->save();

        return $user;
    }

    /**
     * @return array{
     *     upcoming: array<int, array{visit_id: int, agency_name: string, agency_slug: string|null, booking_token: string|null, description: string|null, service_date: string, service_time: string, status: string}>,
     *     invoices: array<int, array{id: int, number: string, agency_name: string, agency_slug: string|null, service_date: string, total_pence: int, status: string, pdf_url: string|null}>,
     *     place: array{lat: float, lng: float}|null
     * }
     */
    public function home(User $user): array
    {
        $clients = Client::query()->where('user_id', $user->id)->get();
        $place = $clients->first(fn (Client $client): bool => $client->lat !== null);
        $clientIds = $clients->pluck('id');

        $upcoming = Visit::query()
            ->whereIn('client_id', $clientIds)
            ->with(['agency', 'client'])
            ->whereIn('status', [VisitStatus::Offered, VisitStatus::Todo, VisitStatus::EnRoute, VisitStatus::CheckedIn])
            ->orderBy('service_date')
            ->orderBy('service_time')
            ->get();

        $invoices = Invoice::query()
            ->whereIn('client_id', $clientIds)
            ->where('status', '!=', InvoiceStatus::ToSend)
            ->with(['agency', 'lines'])
            ->orderByDesc('created_at')
            ->get();

        $doneVisits = Visit::query()
            ->whereIn('client_id', $clientIds)
            ->with(['agency', 'client'])
            ->where('status', VisitStatus::Done)
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $reviewed = Review::query()->whereIn('visit_id', $doneVisits->pluck('id'))->pluck('visit_id')->all();

        return [
            'upcoming' => $upcoming->map(fn (Visit $visit): array => [
                'visit_id' => $visit->id,
                'agency_name' => $visit->agency->name,
                'agency_slug' => $visit->agency->public_slug,
                'booking_token' => $visit->agency->booking_token,
                'description' => $visit->description,
                'service_date' => $visit->service_date->toDateString(),
                'service_time' => $visit->service_time?->format('H:i'),
                'status' => $visit->status->value,
            ])->values()->all(),
            'invoices' => $invoices->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => sprintf('INV-%04d', $invoice->number),
                'agency_name' => $invoice->agency->name,
                'agency_slug' => $invoice->agency->public_slug,
                'service_date' => $invoice->lines->min(fn ($line) => $line->service_date->toDateString()),
                'total_pence' => $invoice->total_pence,
                'status' => $invoice->status->value,
                'pdf_url' => $invoice->pdf_path ? route('invoices.client.pdf', ['invoice' => $invoice->id]) : null,
            ])->values()->all(),
            'place' => $place === null ? null : [
                'lat' => (float) $place->lat,
                'lng' => (float) $place->lng,
            ],
        ];
    }

    /**
     * @param  array{visit_id: int, rating: int, comment?: string|null}  $input
     */
    public function review(User $user, array $input): Review
    {
        $visit = Visit::query()->with('client')->find($input['visit_id']);

        if (
            $visit === null
            || $visit->status !== VisitStatus::Done
            || $visit->client->user_id !== $user->id
        ) {
            throw new ApiException(ErrorCodes::REVIEW_INVALID, 422);
        }

        if (Review::query()->where('visit_id', $visit->id)->exists()) {
            throw new ApiException(ErrorCodes::REVIEW_INVALID, 422);
        }

        $rating = (int) $input['rating'];
        if ($rating < 1 || $rating > 5) {
            throw new ApiException(ErrorCodes::REVIEW_INVALID, 422);
        }

        $review = Review::query()->create([
            'agency_id' => $visit->agency_id,
            'client_id' => $visit->client_id,
            'visit_id' => $visit->id,
            'rating' => $rating,
            'comment' => $input['comment'] ?? null,
        ]);

        $average = Review::query()->where('agency_id', $visit->agency_id)->avg('rating');
        $visit->agency()->update(['average_rating' => round((float) $average, 2)]);

        return $review;
    }

    public function avatar(User $user, ?UploadedFile $file): void
    {
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw new ApiException(ErrorCodes::AGENCY_LOGO_INVALID, 422);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true) || ! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new ApiException(ErrorCodes::AGENCY_LOGO_INVALID, 422);
        }

        $clients = Client::query()->where('user_id', $user->id)->get();
        if ($clients->isEmpty()) {
            throw new ApiException(ErrorCodes::CLIENT_JOIN_INVALID, 403);
        }

        StoredImage::shrink($file, StoredImage::AVATAR);
        $path = $file->store('clients/'.$user->id, 'local');

        foreach ($clients as $client) {
            if (filled($client->avatar_path)) {
                Storage::disk('local')->delete($client->avatar_path);
            }
            $client->avatar_path = $path;
            $client->save();
        }
    }

    private function client(string $token): Client
    {
        $client = Client::query()->where('join_token', $token)->first();

        if ($client === null) {
            throw new ApiException(ErrorCodes::CLIENT_JOIN_INVALID, 404);
        }

        return $client;
    }
}
