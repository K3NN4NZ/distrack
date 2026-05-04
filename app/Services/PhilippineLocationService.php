<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PhilippineLocationService
{
    protected const BASE_URL = 'https://barangays.sanchez.ph/downloads';

    /**
     * List of provinces, plus NCR as a pseudo-province option.
     */
    public function provinces(): Collection
    {
        return $this->cachedDataset('provinces', 'provinces.json')
            ->map(fn (array $province): array => [
                'code' => (string) $province['code'],
                'name' => (string) $province['name'],
            ])
            ->push([
                'code' => '1300000000',
                'name' => 'Metro Manila (NCR)',
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * List of cities and municipalities for a given province or NCR.
     */
    public function citiesByProvince(?string $provinceCode): Collection
    {
        if (! filled($provinceCode)) {
            return collect();
        }

        return $this->cachedDataset('cities', 'cities.json')
            ->filter(function (array $city) use ($provinceCode): bool {
                if ($provinceCode === '1300000000') {
                    return (string) ($city['region_code'] ?? '') === '1300000000';
                }

                return (string) ($city['province_code'] ?? '') === $provinceCode;
            })
            ->map(fn (array $city): array => [
                'code' => (string) $city['code'],
                'name' => (string) $city['name'],
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * List of barangays for a given city or municipality.
     */
    public function barangaysByCity(?string $cityCode): Collection
    {
        if (! filled($cityCode)) {
            return collect();
        }

        return $this->cachedDataset('barangays', 'barangays.json')
            ->filter(fn (array $barangay): bool => (string) ($barangay['city_code'] ?? '') === $cityCode)
            ->map(fn (array $barangay): array => [
                'code' => (string) $barangay['code'],
                'name' => (string) $barangay['name'],
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Cached remote PSGC dataset.
     */
    protected function cachedDataset(string $key, string $path): Collection
    {
        return Cache::remember(
            "philippine-locations:{$key}",
            now()->addDay(),
            fn (): Collection => $this->fetchDataset($path),
        );
    }

    /**
     * Download a remote dataset and normalize it to a collection.
     */
    protected function fetchDataset(string $path): Collection
    {
        $response = Http::acceptJson()
            ->timeout(20)
            ->retry(2, 250)
            ->get(self::BASE_URL.'/'.$path);

        if (! $response->successful()) {
            return collect();
        }

        $payload = $response->json();

        return collect(is_array($payload) ? $payload : []);
    }
}
