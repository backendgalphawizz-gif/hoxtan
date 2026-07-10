<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Services\AppSettingService;
use App\Support\ApiResponse;
use App\Support\DriverAppConfigPayload;
use Illuminate\Http\JsonResponse;

class DriverAppConfigController extends Controller
{
    public function index(AppSettingService $settings): JsonResponse
    {
        return ApiResponse::success(DriverAppConfigPayload::make($settings));
    }
}
