<?php

namespace App\Actions\Field;

use App\Enums\MembershipRole;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\StoredImage;
use Illuminate\Http\UploadedFile;

class StorePhoto
{
    public function __invoke(Visit $visit, ?UploadedFile $photo): VisitPhoto
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        $isOwner = $membership->role === MembershipRole::Owner;
        $isAssignee = $visit->assignee_id === $actor->id;

        if ($membership->agency_id !== $visit->agency_id || (! $isOwner && ! $isAssignee)) {
            throw new ApiException(ErrorCodes::VISIT_NOT_ASSIGNEE, 403);
        }

        $count = $visit->photos()->count();

        if ($count >= 3) {
            throw new ApiException(ErrorCodes::VISIT_PHOTO_LIMIT, 422);
        }

        StoredImage::shrink($photo);
        $name = 'photo_'.($count + 1).'.'.$photo->extension();
        $path = $photo->storeAs('visits/'.$visit->id, $name, 'local');

        return VisitPhoto::query()->create([
            'visit_id' => $visit->id,
            'path' => $path,
        ]);
    }
}
