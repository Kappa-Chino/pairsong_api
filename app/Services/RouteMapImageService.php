<?php

namespace App\Services;

use App\Models\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RouteMapImageService
{
    private const MAX_PATH_POINTS = 250;

    /**
     * Render the route shown in the admin map as a temporary Google Maps image.
     * The caller should delete the image as soon as video rendering is complete.
     */
    public function render(Route $route, string $targetPath): bool
    {
        $apiKey = config('services.google_maps.static_key');
        if (!$apiKey) {
            Log::warning('Google Maps Static API key is not configured; route map image was not generated.', [
                'route_id' => $route->id,
            ]);
            return false;
        }

        $points = $route->routePoints()
            ->orderBy('date')
            ->get(['latitude', 'longitude'])
            ->map(fn ($point) => [
                'lat' => (float) $point->latitude,
                'lng' => (float) $point->longitude,
            ])
            ->filter(fn ($point) => is_finite($point['lat']) && is_finite($point['lng'])
                && $point['lat'] >= -90 && $point['lat'] <= 90
                && $point['lng'] >= -180 && $point['lng'] <= 180)
            ->values();

        if ($points->isEmpty()) {
            Log::warning('No valid route points were available for the route map image.', ['route_id' => $route->id]);
            return false;
        }

        $sampledPoints = $this->samplePoints($points->all());
        $query = [
            'size' => '360x640',
            'scale' => 2,
            'format' => 'png',
            'maptype' => 'roadmap',
            'language' => 'ja',
            'region' => 'JP',
            'key' => $apiKey,
        ];

        if (count($sampledPoints) >= 2) {
            $query['path'] = 'weight:5|color:0x4285F4|enc:' . $this->encodePolyline($sampledPoints);
        } else {
            $query['center'] = $sampledPoints[0]['lat'] . ',' . $sampledPoints[0]['lng'];
            $query['zoom'] = 15;
        }

        $visitedSpots = DB::table('visited_spots')
            ->join('spots', 'visited_spots.spot_id', '=', 'spots.id')
            ->where('visited_spots.route_id', $route->id)
            ->orderBy('visited_spots.enter_time')
            ->get(['visited_spots.spot_id', 'spots.latitude', 'spots.longitude'])
            ->unique('spot_id')
            ->filter(fn ($spot) => is_numeric($spot->latitude) && is_numeric($spot->longitude)
                && (float) $spot->latitude >= -90 && (float) $spot->latitude <= 90
                && (float) $spot->longitude >= -180 && (float) $spot->longitude <= 180)
            ->values();

        if ($visitedSpots->isNotEmpty()) {
            $iconUrl = config('services.google_maps.marker_icon_url') ?: asset('assets/img/azalea-marker.png');
            $locations = $visitedSpots
                ->map(fn ($spot) => (float) $spot->latitude . ',' . (float) $spot->longitude)
                ->implode('|');
            $query['markers'] = 'anchor:bottom|icon:' . $iconUrl . '|' . $locations;
        }

        try {
            $response = Http::timeout(30)->get('https://maps.googleapis.com/maps/api/staticmap', $query);
        } catch (\Throwable $exception) {
            Log::warning('Google Maps Static API request failed.', [
                'route_id' => $route->id,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }

        $image = $response->body();
        if (!$response->successful()
            || !str_starts_with($response->header('Content-Type', ''), 'image/')
            || $response->header('X-Staticmap-API-Warning')
            || strlen($image) < 1000) {
            Log::warning('Google Maps Static API did not return a usable map image.', [
                'route_id' => $route->id,
                'status' => $response->status(),
                'warning' => $response->header('X-Staticmap-API-Warning'),
            ]);
            return false;
        }

        $directory = dirname($targetPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            Log::error('Could not create temporary route map directory.', ['path' => $directory]);
            return false;
        }

        return file_put_contents($targetPath, $image) !== false;
    }

    /** @param array<int, array{lat: float, lng: float}> $points */
    private function samplePoints(array $points): array
    {
        if (count($points) <= self::MAX_PATH_POINTS) {
            return $points;
        }

        $sampled = [];
        $lastIndex = count($points) - 1;
        for ($index = 0; $index < self::MAX_PATH_POINTS; $index++) {
            $sampled[] = $points[(int) round($index * $lastIndex / (self::MAX_PATH_POINTS - 1))];
        }

        return $sampled;
    }

    /** @param array<int, array{lat: float, lng: float}> $points */
    private function encodePolyline(array $points): string
    {
        $encoded = '';
        $previousLatitude = 0;
        $previousLongitude = 0;

        foreach ($points as $point) {
            $latitude = (int) round($point['lat'] * 100000);
            $longitude = (int) round($point['lng'] * 100000);
            $encoded .= $this->encodePolylineValue($latitude - $previousLatitude);
            $encoded .= $this->encodePolylineValue($longitude - $previousLongitude);
            $previousLatitude = $latitude;
            $previousLongitude = $longitude;
        }

        return $encoded;
    }

    private function encodePolylineValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $encoded = '';

        while ($value >= 0x20) {
            $encoded .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }

        return $encoded . chr($value + 63);
    }
}
