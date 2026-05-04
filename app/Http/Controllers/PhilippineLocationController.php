<?php

namespace App\Http\Controllers;

use App\Services\PhilippineLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhilippineLocationController extends Controller
{
    /**
     * Return the Philippine province list.
     */
    public function provinces(PhilippineLocationService $locationService): JsonResponse
    {
        return response()->json([
            'data' => $locationService->provinces()->values()->all(),
        ]);
    }

    /**
     * Return cities and municipalities for a province.
     */
    public function cities(Request $request, PhilippineLocationService $locationService): JsonResponse
    {
        $validated = $request->validate([
            'province_code' => ['nullable', 'string'],
        ]);

        return response()->json([
            'data' => $locationService->citiesByProvince($validated['province_code'] ?? null)->values()->all(),
        ]);
    }

    /**
     * Return barangays for a city or municipality.
     */
    public function barangays(Request $request, PhilippineLocationService $locationService): JsonResponse
    {
        $validated = $request->validate([
            'city_code' => ['nullable', 'string'],
        ]);

        return response()->json([
            'data' => $locationService->barangaysByCity($validated['city_code'] ?? null)->values()->all(),
        ]);
    }
}
