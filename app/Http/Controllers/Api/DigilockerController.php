<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KycService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DigilockerController extends Controller
{
    public function initialize(Request $request, KycService $kyc): JsonResponse
    {
        $this->assertSurepassProvider();

        $nested = $request->input('data');
        $input = is_array($nested) ? $nested : $request->all();

        $data = validator($input, [
            'signup_flow' => ['nullable', 'boolean'],
            'auth_type' => ['nullable', 'string', 'max:20'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'voice_assistant_lang' => ['nullable', 'string', 'max:10'],
            'voice_assistant' => ['nullable', 'boolean'],
            'retry_count' => ['nullable', 'integer', 'min:1', 'max:5'],
            'skip_main_screen' => ['nullable', 'boolean'],
        ])->validate();

        $result = $kyc->initializeDigilocker($request->user(), $data);

        return ApiResponse::success($result, 'DigiLocker session initialized successfully.');
    }

    public function status(Request $request, string $clientId, KycService $kyc): JsonResponse
    {
        $this->assertSurepassProvider();

        $result = $kyc->checkDigilockerStatus($request->user(), $clientId);

        $message = ($result['verified'] ?? false)
            ? 'Aadhaar verified successfully via DigiLocker.'
            : (string) ($result['message'] ?? 'DigiLocker status fetched.');

        return ApiResponse::success($result, $message);
    }

    protected function assertSurepassProvider(): void
    {
        if (config('kyc.provider') !== 'surepass') {
            throw ValidationException::withMessages([
                'digilocker' => ['DigiLocker Aadhaar verification is only available with Surepass.'],
            ]);
        }
    }
}
