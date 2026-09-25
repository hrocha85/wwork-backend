<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class ResetPassword
{
    /**
     * @param  array{email: string, token: string, password: string, password_confirmation: string}  $data
     */
    public function __invoke(array $data): void
    {
        if ($data['password'] !== $data['password_confirmation']) {
            throw new ApiException(ErrorCodes::AUTH_PASSWORD_MISMATCH, 422);
        }

        $row = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        if ($row === null) {
            throw new ApiException(ErrorCodes::AUTH_RESET_INVALID, 404);
        }

        $createdAt = Carbon::parse($row->created_at);
        $minutes = (int) config('auth.passwords.users.expire');

        if ($createdAt->addMinutes($minutes)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

            throw new ApiException(ErrorCodes::AUTH_RESET_EXPIRED, 404);
        }

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || $user->membership === null || ! Password::broker()->tokenExists($user, $data['token'])) {
            throw new ApiException(ErrorCodes::AUTH_RESET_INVALID, 404);
        }

        $user->password = $data['password'];
        $user->must_change_password = false;
        $user->save();

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        $user->loadMissing('membership');
        RecordActivity::add($user->membership?->agency_id, $user->id, 'auth.password_reset');
    }
}
