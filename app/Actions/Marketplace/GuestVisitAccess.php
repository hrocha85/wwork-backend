<?php

namespace App\Actions\Marketplace;

use App\Enums\InvoiceStatus;
use App\Enums\VisitStatus;
use App\Mail\AccessCodeMail;
use App\Models\Client;
use App\Models\ClientAccessCode;
use App\Models\Invoice;
use App\Models\Review;
use App\Models\Visit;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\MailNotifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuestVisitAccess
{
    public function issue(Visit $visit): string
    {
        if (! filled($visit->guest_token) || $visit->guest_token_expires_at === null || $visit->guest_token_expires_at->isPast()) {
            $visit->guest_token = Str::random(48);
            $visit->guest_token_expires_at = now()->addDays(7);
            $visit->save();
        }

        return (string) $visit->guest_token;
    }

    public function url(Visit $visit): string
    {
        return rtrim((string) config('wwork.frontend_url'), '/').'/visit/'.$this->issue($visit);
    }

    public function show(string $token): array
    {
        $visit = $this->visit($token);

        return $this->present($visit);
    }

    public function present(Visit $visit): array
    {
        $visit->loadMissing(['client', 'agency', 'assignee', 'photos', 'events', 'invoiceLine.invoice']);
        $invoice = $visit->invoiceLine?->invoice;
        if ($invoice !== null && $invoice->status === InvoiceStatus::ToSend) {
            $invoice = null;
        }
        $review = Review::query()->where('visit_id', $visit->id)->first();
        $tracking = null;
        if (in_array($visit->status, [VisitStatus::EnRoute, VisitStatus::CheckedIn], true) && $visit->assignee !== null) {
            $tracking = [
                'assignee_name' => $visit->assignee->name,
                'lat' => $visit->assignee->last_lat === null ? null : (float) $visit->assignee->last_lat,
                'lng' => $visit->assignee->last_lng === null ? null : (float) $visit->assignee->last_lng,
                'at' => $visit->assignee->last_located_at?->toIso8601String(),
            ];
        }
        $base = rtrim((string) config('app.url'), '/');

        return [
            'visit_id' => $visit->id,
            'agency_name' => $visit->agency->name,
            'agency_slug' => $visit->agency->public_slug,
            'description' => $visit->description,
            'service_date' => $visit->service_date->toDateString(),
            'service_time' => substr((string) $visit->service_time, 0, 5),
            'status' => $visit->status->value,
            'price_pence' => $visit->price_pence,
            'currency' => $visit->agency->currency,
            'tracking' => $tracking,
            'photos' => $visit->photos->map(fn ($photo): array => [
                'id' => $photo->id,
                'url' => $base.'/api/v1/guest/visits/'.$visit->guest_token.'/photos/'.$photo->id,
            ])->values()->all(),
            'events' => $visit->events->map(fn ($event): array => [
                'type' => $event->type->value,
                'at' => $event->occurred_at?->toIso8601String(),
            ])->values()->all(),
            'reviewed' => $review !== null,
            'rating' => $review?->rating,
            'invoice' => $invoice === null ? null : [
                'id' => $invoice->id,
                'number' => sprintf('INV-%04d', $invoice->number),
                'total_pence' => $invoice->total_pence,
                'status' => $invoice->status->value,
                'pdf_url' => $base.'/api/v1/guest/visits/'.$visit->guest_token.'/invoice.pdf',
            ],
            'has_account' => $visit->client->user_id !== null,
            'client_email' => $visit->client->email,
        ];
    }

    /**
     * @param  array{rating: int, comment?: string|null, email?: string|null}  $input
     */
    public function review(string $token, array $input): Review
    {
        $visit = $this->visit($token);
        $visit->loadMissing('client');

        if ($visit->status !== VisitStatus::Done) {
            throw new ApiException(ErrorCodes::REVIEW_INVALID, 422);
        }
        if (Review::query()->where('visit_id', $visit->id)->exists()) {
            throw new ApiException(ErrorCodes::REVIEW_INVALID, 422);
        }

        $email = filled($input['email'] ?? null) ? trim((string) $input['email']) : null;
        if ($email !== null && ! filled($visit->client->email)) {
            $visit->client->email = $email;
            $visit->client->save();
        }

        $review = Review::query()->create([
            'agency_id' => $visit->agency_id,
            'client_id' => $visit->client_id,
            'visit_id' => $visit->id,
            'rating' => (int) $input['rating'],
            'comment' => $input['comment'] ?? null,
        ]);

        $average = Review::query()->where('agency_id', $visit->agency_id)->avg('rating');
        $visit->agency()->update(['average_rating' => round((float) $average, 2)]);

        return $review;
    }

    public function declineInvite(string $token): void
    {
        $visit = $this->visit($token);
        $visit->client->forceFill(['profile_invite_declined_at' => now()])->save();
    }

    public function requestCode(string $email): void
    {
        $email = Str::lower(trim($email));
        $exists = Client::query()->whereRaw('LOWER(email) = ?', [$email])->exists();
        if (! $exists) {
            return;
        }

        $code = (string) random_int(100000, 999999);
        ClientAccessCode::query()->create([
            'email' => $email,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(15),
        ]);

        app(MailNotifier::class)->send('client.access_code', $email, null, new AccessCodeMail($code));
    }

    /**
     * @return array{visits: array<int, mixed>, invoices: array<int, mixed>}
     */
    public function history(string $email, string $code): array
    {
        $email = Str::lower(trim($email));
        $row = ClientAccessCode::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if ($row === null || ! hash_equals($row->code_hash, hash('sha256', trim($code)))) {
            throw new ApiException('client.access_code_invalid', 422);
        }
        $row->forceFill(['consumed_at' => now()])->save();

        $clients = Client::query()->whereRaw('LOWER(email) = ?', [$email])->get();
        $clientIds = $clients->pluck('id');

        $visits = Visit::query()
            ->whereIn('client_id', $clientIds)
            ->with(['agency', 'client'])
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $invoices = Invoice::query()
            ->whereIn('client_id', $clientIds)
            ->where('status', '!=', InvoiceStatus::ToSend)
            ->with(['agency', 'lines'])
            ->orderByDesc('created_at')
            ->get();

        return [
            'visits' => $visits->map(fn (Visit $visit): array => [
                'visit_id' => $visit->id,
                'agency_name' => $visit->agency->name,
                'description' => $visit->description,
                'service_date' => $visit->service_date->toDateString(),
                'service_time' => substr((string) $visit->service_time, 0, 5),
                'status' => $visit->status->value,
                'guest_url' => filled($visit->guest_token) && $visit->guest_token_expires_at !== null && $visit->guest_token_expires_at->isFuture()
                    ? rtrim((string) config('wwork.frontend_url'), '/').'/visit/'.$visit->guest_token
                    : null,
            ])->values()->all(),
            'invoices' => $invoices->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => sprintf('INV-%04d', $invoice->number),
                'agency_name' => $invoice->agency->name,
                'currency' => $invoice->agency->currency,
                'service_date' => $invoice->lines->min(fn ($line) => $line->service_date->toDateString()),
                'total_pence' => $invoice->total_pence,
                'status' => $invoice->status->value,
            ])->values()->all(),
        ];
    }

    public function visit(string $token): Visit
    {
        $visit = Visit::query()->where('guest_token', $token)->first();

        if ($visit === null || $visit->guest_token_expires_at === null || $visit->guest_token_expires_at->isPast()) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return $visit;
    }
}
