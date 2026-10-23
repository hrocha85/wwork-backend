<?php

namespace App\Actions\Marketplace;

use App\Enums\SubscriptionStatus;
use App\Enums\VisitStatus;
use App\Models\Agency;
use App\Models\Client;
use App\Models\PortfolioPhoto;
use App\Models\Review;
use App\Support\ApiException;
use App\Support\ErrorCodes;

class ShowPublicAgency
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(string $slug): array
    {
        $agency = $this->agency($slug);
        $ready = filled($agency->booking_token)
            && $agency->bookingServices->isNotEmpty()
            && $agency->bookingHours()->exists();
        $status = $agency->subscription?->status;

        $reviews = $agency->reviews;

        return [
            'name' => $agency->name,
            'bio' => $agency->bio,
            'website' => $agency->website,
            'phone' => $agency->phone,
            'has_logo' => filled($agency->logo_path),
            'trade' => $agency->trade?->value,
            'verified' => in_array($status, [
                SubscriptionStatus::Active,
                SubscriptionStatus::Complimentary,
                SubscriptionStatus::PaidOffline,
            ], true),
            'booking_ready' => $ready,
            'booking_token' => $ready ? $agency->booking_token : null,
            'currency' => $agency->currency,
            'average_rating' => (float) $agency->average_rating,
            'review_count' => $reviews->count(),
            'completed_count' => $agency->visits()
                ->where('status', VisitStatus::Done)
                ->count(),
            'rating_distribution' => $this->ratingDistribution($reviews),
            'services' => $agency->bookingServices->map(fn ($service): array => [
                'name' => $service->name,
                'price_pence' => $service->price_pence,
            ])->values()->all(),
            'photos' => $agency->portfolioPhotos->map(fn (PortfolioPhoto $photo): array => [
                'id' => $photo->id,
                'caption' => $photo->caption,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{reviews: array<int, array<string, mixed>>}
     */
    public function reviews(string $slug): array
    {
        $agency = $this->agency($slug);

        return [
            'reviews' => $agency->reviews
                ->sortByDesc('created_at')
                ->map(fn (Review $review): array => [
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'client_name' => $review->client->name,
                    'client_id' => $review->client_id,
                    'has_avatar' => filled($review->client->avatar_path),
                    'created_at' => $review->created_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }

    public function agency(string $slug): Agency
    {
        $agency = Agency::query()
            ->where('public_slug', $slug)
            ->with(['bookingServices', 'portfolioPhotos', 'reviews.client', 'subscription'])
            ->first();

        if ($agency === null) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return $agency;
    }

    /**
     * @return array<int, int>
     */
    private function ratingDistribution(\Illuminate\Support\Collection $reviews): array
    {
        $counts = $reviews->groupBy('rating')->map->count();

        return collect(range(1, 5))->mapWithKeys(
            fn (int $star): array => [$star => $counts->get($star, 0)]
        )->all();
    }
}
