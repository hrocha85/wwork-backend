<?php

namespace App\Actions\Marketplace;

use App\Models\Agency;
use Illuminate\Support\Facades\DB;

class SearchAgencies
{
    /**
     * @return array{agencies: array<int, array<string, mixed>>}
     */
    public function __invoke(?string $term, mixed $lat, mixed $lng): array
    {
        $query = Agency::query()->with(['bookingServices', 'subscription'])->whereNotNull('public_slug');
        $needle = trim((string) $term);

        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('trade_detail', 'like', $like)
                    ->orWhere('trade', 'like', $like)
                    ->orWhereHas('bookingServices', fn ($services) => $services->where('name', 'like', $like));
            });
        }

        $originLat = is_numeric($lat) ? (float) $lat : null;
        $originLng = is_numeric($lng) ? (float) $lng : null;
        $driver = DB::connection()->getDriverName();

        if ($originLat !== null && $originLng !== null && in_array($driver, ['mysql', 'mariadb'], true)) {
            $query->select('agencies.*')->selectRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) as distance_km',
                [$originLat, $originLng, $originLat],
            );
        }

        $rows = $query->limit(40)->get()->map(function (Agency $agency) use ($originLat, $originLng): array {
            return $this->card($agency, $originLat, $originLng);
        });

        $sorted = $rows->sort(function (array $left, array $right): int {
            $rating = ((float) $right['average_rating']) <=> ((float) $left['average_rating']);
            if ($rating !== 0) {
                return $rating;
            }
            $leftDistance = $left['distance_km'];
            $rightDistance = $right['distance_km'];
            if ($leftDistance === null && $rightDistance === null) {
                return 0;
            }
            if ($leftDistance === null) {
                return 1;
            }
            if ($rightDistance === null) {
                return -1;
            }

            return $leftDistance <=> $rightDistance;
        })->values();

        return ['agencies' => $sorted->all()];
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Agency $agency, ?float $lat, ?float $lng): array
    {
        $service = $agency->bookingServices->first();
        $distance = null;

        if ($lat !== null && $lng !== null && $agency->latitude !== null && $agency->longitude !== null) {
            $distance = round($this->kilometres($lat, $lng, (float) $agency->latitude, (float) $agency->longitude), 1);
        }

        return [
            'slug' => $agency->public_slug,
            'name' => $agency->name,
            'service' => $service?->name ?? $agency->trade_detail ?? $agency->trade->value,
            'average_rating' => (float) $agency->average_rating,
            'has_logo' => filled($agency->logo_path),
            'distance_km' => $distance,
        ];
    }

    private function kilometres(float $lat, float $lng, float $agencyLat, float $agencyLng): float
    {
        $earth = 6371;
        $latDelta = deg2rad($agencyLat - $lat);
        $lngDelta = deg2rad($agencyLng - $lng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat)) * cos(deg2rad($agencyLat)) * sin($lngDelta / 2) ** 2;

        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
