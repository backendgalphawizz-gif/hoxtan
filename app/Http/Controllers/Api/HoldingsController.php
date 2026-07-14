<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HoldingsPerformanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HoldingsController extends Controller
{
    public function config(HoldingsPerformanceService $service): JsonResponse
    {
        return ApiResponse::success($service->config());
    }

    public function performance(Request $request, HoldingsPerformanceService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'metal_type' => ['nullable', Rule::in(['gold', 'silver'])],
            'months' => ['nullable', 'integer', Rule::in([12, 24, 36])],
        ]);

        return ApiResponse::success($service->performance(
            $user,
            $data['metal_type'] ?? (string) config('holdings.default_metal_type', 'gold'),
            (int) ($data['months'] ?? config('holdings.default_months', 12)),
        ));
    }
}
