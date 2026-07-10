<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Support\ApiResponse;
use App\Support\DriverPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        return ApiResponse::success([
            'driver' => DriverPayload::make($driver),
        ]);
    }
}
