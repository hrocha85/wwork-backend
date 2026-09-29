<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Marketplace\ShowPublicAgency;
use App\Actions\Marketplace\UpdatePublicProfile;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PortfolioPhoto;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicAgencyController extends Controller
{
    public function show(string $slug, ShowPublicAgency $show): JsonResponse
    {
        return response()->json($show($slug));
    }

    public function reviews(string $slug, ShowPublicAgency $show): JsonResponse
    {
        return response()->json($show->reviews($slug));
    }

    public function logo(string $slug, ShowPublicAgency $show): StreamedResponse
    {
        return $this->file($show->agency($slug)->logo_path);
    }

    public function photo(string $slug, int $photo, ShowPublicAgency $show): StreamedResponse
    {
        $agency = $show->agency($slug);
        $row = PortfolioPhoto::query()->where('agency_id', $agency->id)->find($photo);

        if ($row === null) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return $this->file($row->path);
    }

    public function avatar(string $slug, int $client, ShowPublicAgency $show): StreamedResponse
    {
        $agency = $show->agency($slug);
        $row = Client::query()->where('agency_id', $agency->id)->find($client);

        if ($row === null) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return $this->file($row->avatar_path);
    }

    public function update(Request $request, UpdatePublicProfile $update): JsonResponse
    {
        $agency = $update($request->validate([
            'bio' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'string', 'max:200'],
            'public_slug' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]));

        return response()->json([
            'public_slug' => $agency->public_slug,
            'bio' => $agency->bio,
            'website' => $agency->website,
        ]);
    }

    public function storePhoto(Request $request, UpdatePublicProfile $update): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file'],
            'caption' => ['nullable', 'string', 'max:160'],
        ]);
        $photo = $update->photo($request->file('photo'), $request->input('caption'));

        return response()->json(['id' => $photo->id, 'caption' => $photo->caption], 201);
    }

    public function destroyPhoto(int $photo, UpdatePublicProfile $update): Response
    {
        $update->deletePhoto($photo);

        return response()->noContent();
    }

    private function file(mixed $path): StreamedResponse
    {
        if (! is_string($path) || $path === '' || ! Storage::disk('local')->exists($path)) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return Storage::disk('local')->response($path);
    }
}
