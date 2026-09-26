<?php

namespace App\Actions\Agency;

use App\Enums\MembershipRole;
use App\Models\Agency;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreAgencyLogo
{
    public function __invoke(?UploadedFile $logo): Agency
    {
        $membership = AgencyContext::membership();

        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::AGENCY_NOT_OWNER, 403);
        }

        if (! $logo instanceof UploadedFile || ! $logo->isValid()) {
            throw new ApiException(ErrorCodes::AGENCY_LOGO_INVALID, 422);
        }

        $extension = strtolower($logo->getClientOriginalExtension());
        $mime = (string) $logo->getMimeType();

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true) || ! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new ApiException(ErrorCodes::AGENCY_LOGO_INVALID, 422);
        }

        if ($logo->getSize() > 2 * 1024 * 1024) {
            throw new ApiException(ErrorCodes::AGENCY_LOGO_INVALID, 422);
        }

        $agency = $membership->agency;

        if (filled($agency->logo_path)) {
            Storage::disk('local')->delete($agency->logo_path);
        }

        $path = $logo->storeAs('agencies/'.$agency->id, 'logo.'.$extension, 'local');
        $agency->forceFill(['logo_path' => $path])->save();
        RecordActivity::add($agency->id, AgencyContext::user()->id, 'agency.logo');

        return $agency;
    }
}
