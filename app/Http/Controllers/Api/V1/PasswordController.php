<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\ChangePassword;
use App\Actions\Auth\ResetPassword;
use App\Actions\Auth\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    public function change(ChangePasswordRequest $request, ChangePassword $changePassword): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $changePassword($user, $request->validated());

        return response()->json(['ok' => true]);
    }

    public function forgot(ForgotPasswordRequest $request, SendPasswordResetLink $send): JsonResponse
    {
        $send($request->string('email')->toString());

        return response()->json(['ok' => true]);
    }

    public function reset(ResetPasswordRequest $request, ResetPassword $reset): JsonResponse
    {
        $reset($request->validated());

        return response()->json(['ok' => true]);
    }
}
