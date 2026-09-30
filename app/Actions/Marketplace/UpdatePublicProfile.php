<?php

namespace App\Actions\Marketplace;

use App\Enums\MembershipRole;
use App\Models\Agency;
use App\Models\PortfolioPhoto;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\StoredImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdatePublicProfile
{
    /**
     * @param  array{bio?: string|null, website?: string|null, public_slug?: string|null, latitude?: mixed, longitude?: mixed}  $input
     */
    public function __invoke(array $input): Agency
    {
        $agency = $this->owned();
        $slug = $this->slug($agency, $input['public_slug'] ?? $agency->public_slug);

        $agency->forceFill([
            'bio' => $input['bio'] ?? $agency->bio,
            'website' => $input['website'] ?? $agency->website,
            'public_slug' => $slug,
            'latitude' => array_key_exists('latitude', $input) && is_numeric($input['latitude']) ? $input['latitude'] : $agency->latitude,
            'longitude' => array_key_exists('longitude', $input) && is_numeric($input['longitude']) ? $input['longitude'] : $agency->longitude,
        ])->save();

        return $agency;
    }

    public function photo(?UploadedFile $file, ?string $caption): PortfolioPhoto
    {
        $agency = $this->owned();

        if ($agency->portfolioPhotos()->count() >= 12) {
            throw new ApiException(ErrorCodes::AGENCY_LOGO_INVALID, 422);
        }

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw new ApiException(ErrorCodes::AGENCY_LOGO_INVALID, 422);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true) || ! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new ApiException(ErrorCodes::AGENCY_LOGO_INVALID, 422);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new ApiException(ErrorCodes::AGENCY_LOGO_INVALID, 422);
        }

        StoredImage::shrink($file);
        $path = $file->store('agencies/'.$agency->id.'/portfolio', 'local');

        return $agency->portfolioPhotos()->create([
            'path' => $path,
            'caption' => $caption,
        ]);
    }

    public function deletePhoto(int $photoId): void
    {
        $agency = $this->owned();
        $photo = PortfolioPhoto::query()->where('agency_id', $agency->id)->find($photoId);

        if ($photo === null) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        Storage::disk('local')->delete($photo->path);
        $photo->delete();
    }

    private function owned(): Agency
    {
        $membership = AgencyContext::membership();

        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::AGENCY_NOT_OWNER, 403);
        }

        return $membership->agency;
    }

    private function slug(Agency $agency, mixed $requested): string
    {
        $base = Str::slug(is_string($requested) && $requested !== '' ? $requested : (string) $agency->name);
        // `GET /agency/logo` é a rota do dono logado; um slug igual ficaria inacessível.
        if ($base === '' || $base === 'logo') {
            $base = $base === '' ? 'agency' : 'logo-agency';
        }
        $slug = $base;
        $suffix = 2;

        while (Agency::query()->where('public_slug', $slug)->whereKeyNot($agency->id)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
