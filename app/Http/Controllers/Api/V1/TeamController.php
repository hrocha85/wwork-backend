<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Team\AcceptInvite;
use App\Actions\Team\InvitePartner;
use App\Actions\Team\RemoveMember;
use App\Actions\Team\ResendInvite;
use App\Actions\Team\UpdateRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcceptInviteRequest;
use App\Http\Requests\Api\V1\InvitePartnerRequest;
use App\Http\Requests\Api\V1\UpdateRateRequest;
use App\Http\Resources\Api\V1\TeamResource;
use App\Models\Invite;
use App\Models\User;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TeamController extends Controller
{
    public function store(InvitePartnerRequest $request, InvitePartner $invite): JsonResponse
    {
        $created = $invite(
            $request->string('email')->toString(),
            $request->integer('rate'),
        );

        return response()->json(
            TeamResource::invite($created, AgencyContext::membership()->agency->timezone),
            201,
        );
    }

    public function index(): JsonResponse
    {
        return response()->json(TeamResource::index());
    }

    public function resend(int $invite, ResendInvite $resend): JsonResponse
    {
        $row = $this->inviteInAgency($invite);
        $updated = $resend($row);

        return response()->json(
            TeamResource::invite($updated, AgencyContext::membership()->agency->timezone),
        );
    }

    public function accept(string $token, AcceptInviteRequest $request, AcceptInvite $accept): JsonResponse
    {
        $user = $accept($token, $request->validated());

        return response()->json(TeamResource::accepted($user));
    }

    public function updateRate(int $userId, UpdateRateRequest $request, UpdateRate $update): JsonResponse
    {
        $membership = $update($this->member($userId), $request->integer('rate'));

        return response()->json(TeamResource::rate($membership));
    }

    public function destroy(int $userId, RemoveMember $remove): Response
    {
        $remove($this->member($userId));

        return response()->noContent();
    }

    private function inviteInAgency(int $id): Invite
    {
        $agencyId = AgencyContext::membership()->agency_id;
        $invite = Invite::query()->where('agency_id', $agencyId)->find($id);

        if ($invite === null) {
            throw new ApiException(ErrorCodes::INVITE_NOT_FOUND, 404);
        }

        return $invite;
    }

    private function member(int $userId): User
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            throw new ApiException(ErrorCodes::TEAM_MEMBER_NOT_FOUND, 404);
        }

        return $user;
    }
}
