<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * IP Geolocation Service using ip-api.com (free tier, no API key required)
 * Free tier: 45 requests per minute, HTTP only
 */
class IpGeolocationService
{
    private const API_URL = 'http://ip-api.com/json/';
    private const CACHE_TTL = 86400; // 24 hours in seconds

    /**
     * Get country information for an IP address
     *
     * @param string|null $ip
     * @return array{country: string|null, countryCode: string|null, city: string|null}
     */
    public function getLocation(?string $ip): array
    {
        if (empty($ip) || $this->isPrivateIp($ip)) {
            return [
                'country' => null,
                'countryCode' => null,
                'city' => null,
            ];
        }

        $cacheKey = 'ip_geo_' . md5($ip);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($ip) {
            return $this->fetchFromApi($ip);
        });
    }

    /**
     * Get country name for an IP address
     *
     * @param string|null $ip
     * @return string|null
     */
    public function getCountry(?string $ip): ?string
    {
        return $this->getLocation($ip)['country'];
    }

    /**
     * Fetch location data from ip-api.com
     *
     * @param string $ip
     * @return array
     */
    private function fetchFromApi(string $ip): array
    {
        try {
            $response = Http::timeout(5)->get(self::API_URL . $ip, [
                'fields' => 'status,country,countryCode,city',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'success') {
                    return [
                        'country' => $data['country'] ?? null,
                        'countryCode' => $data['countryCode'] ?? null,
                        'city' => $data['city'] ?? null,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('IP geolocation lookup failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'country' => null,
            'countryCode' => null,
            'city' => null,
        ];
    }

    /**
     * Check if IP is a private/reserved address
     *
     * @param string $ip
     * @return bool
     */
    private function isPrivateIp(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
