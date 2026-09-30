<?php

namespace App\Actions\Team;

use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\StoredImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreUserAvatar
{
    public function __invoke(User $user, ?UploadedFile $photo): User
    {
        if (! $photo instanceof UploadedFile || ! $photo->isValid()) {
            throw new ApiException(ErrorCodes::AGENCY_LOGO_INVALID, 422);
        }

        $extension = strtolower($photo->getClientOriginalExtension());
        $mime = (string) $photo->getMimeType();

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true) || ! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new ApiException(ErrorCodes::AGENCY_LOGO_INVALID, 422);
        }

        if ($photo->getSize() > 5 * 1024 * 1024) {
            throw new ApiException(ErrorCodes::AGENCY_LOGO_INVALID, 422);
        }

        if (filled($user->avatar_path)) {
            Storage::disk('local')->delete($user->avatar_path);
        }

        StoredImage::shrink($photo, StoredImage::AVATAR);
        $path = $photo->storeAs('users/'.$user->id, 'avatar-'.now()->timestamp.'.'.$extension, 'local');
        $user->forceFill(['avatar_path' => $path])->save();

        return $user;
    }
}
