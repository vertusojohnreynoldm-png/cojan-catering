<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Road route between two points for the rider map (menu-order deliveries).
 *
 * Providers, in order: OpenRouteService (only when ORS_API_KEY is set),
 * then the public OSRM server (no key). If both fail, a straight line with
 * the great-circle distance and a rough ETA is returned, marked
 * 'fallback' => true, so the map can draw it dashed and still show a
 * distance.
 *
 * Results are cached for 60 s per (origin rounded to ~50 m, destination),
 * so a rider standing still — or a page refresh — never re-hits the
 * provider. The rider page itself only asks again after drifting ~50 m off
 * the drawn route or every 60 s, and the endpoint is rate-limited per user.
 */
class RouteService
{
    private const CACHE_SECONDS = 60;
    private const TIMEOUT_SECONDS = 6;
    /** Rough urban motorcycle speed for the fallback ETA, km/h. */
    private const FALLBACK_SPEED_KMH = 25;

    /**
     * @param  array{0: float, 1: float}  $from  [lat, lng]
     * @param  array{0: float, 1: float}  $to    [lat, lng]
     * @return array{provider: string, fallback: bool, distance_m: int, duration_s: int, geometry: array<int, array{0: float, 1: float}>}
     */
    public static function route(array $from, array $to): array
    {
        // ~0.0005° ≈ 50 m: small movements reuse the cached route.
        $key = sprintf('route:%.4f,%.4f:%.5f,%.5f', round($from[0] / 0.0005) * 0.0005, round($from[1] / 0.0005) * 0.0005, $to[0], $to[1]);

        return Cache::remember($key, self::CACHE_SECONDS, fn () => self::compute($from, $to));
    }

    private static function compute(array $from, array $to): array
    {
        $provider = config('services.routing.provider', 'auto');
        $orsKey = config('services.routing.ors_key');

        if ($orsKey && in_array($provider, ['auto', 'ors'], true)) {
            if ($route = self::ors($from, $to, $orsKey)) {
                return $route;
            }
        }

        if (in_array($provider, ['auto', 'osrm', 'ors'], true)) {
            if ($route = self::osrm($from, $to)) {
                return $route;
            }
        }

        return self::straightLine($from, $to);
    }

    private static function osrm(array $from, array $to): ?array
    {
        try {
            $base = rtrim(config('services.routing.osrm_url'), '/');
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => 'CojanCatering/1.0 (+https://cojan-catering-production.up.railway.app)'])
                ->get(sprintf('%s/route/v1/driving/%.6F,%.6F;%.6F,%.6F', $base, $from[1], $from[0], $to[1], $to[0]), [
                    'overview'   => 'full',
                    'geometries' => 'geojson',
                ]);

            $route = $response->json('routes.0');
            if (!$response->successful() || $response->json('code') !== 'Ok' || !$route) {
                Log::warning('OSRM routing failed', ['status' => $response->status(), 'code' => $response->json('code')]);

                return null;
            }

            return self::shape('osrm', $route['geometry']['coordinates'] ?? [], $route['distance'], $route['duration']);
        } catch (Throwable $e) {
            Log::warning('OSRM routing error: ' . $e->getMessage());

            return null;
        }
    }

    private static function ors(array $from, array $to, string $key): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Authorization' => $key])
                ->post('https://api.openrouteservice.org/v2/directions/driving-car/geojson', [
                    'coordinates' => [[$from[1], $from[0]], [$to[1], $to[0]]],
                ]);

            $feature = $response->json('features.0');
            if (!$response->successful() || !$feature) {
                Log::warning('ORS routing failed', ['status' => $response->status()]);

                return null;
            }

            $summary = $feature['properties']['summary'] ?? [];

            return self::shape('ors', $feature['geometry']['coordinates'] ?? [], $summary['distance'] ?? 0, $summary['duration'] ?? 0);
        } catch (Throwable $e) {
            Log::warning('ORS routing error: ' . $e->getMessage());

            return null;
        }
    }

    /** GeoJSON [lng, lat] pairs → Leaflet-friendly [lat, lng]. */
    private static function shape(string $provider, array $coordinates, float $distance, float $duration): ?array
    {
        if (count($coordinates) < 2) {
            return null;
        }

        return [
            'provider'   => $provider,
            'fallback'   => false,
            'distance_m' => (int) round($distance),
            'duration_s' => (int) round($duration),
            'geometry'   => array_map(fn ($c) => [round($c[1], 6), round($c[0], 6)], $coordinates),
        ];
    }

    public static function straightLine(array $from, array $to): array
    {
        $meters = self::haversine($from, $to);

        return [
            'provider'   => 'straight-line',
            'fallback'   => true,
            'distance_m' => (int) round($meters),
            'duration_s' => (int) round($meters / (self::FALLBACK_SPEED_KMH * 1000 / 3600)),
            'geometry'   => [[$from[0], $from[1]], [$to[0], $to[1]]],
        ];
    }

    /** Great-circle distance in metres. */
    public static function haversine(array $a, array $b): float
    {
        $r = 6371000;
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($h)));
    }
}
