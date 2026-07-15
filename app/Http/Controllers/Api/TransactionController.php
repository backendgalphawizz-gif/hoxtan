<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountActivityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function config(): JsonResponse
    {
        return ApiResponse::success([
            'filters' => config('account_activity.transaction_filters', []),
            'wallet_sources' => config('account_activity.wallet_sources', []),
            'investment_statuses' => config('account_activity.investment_statuses', []),
        ]);
    }

    public function index(Request $request, AccountActivityService $activity): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['nullable', 'string', Rule::in([
                'all', 'buy', 'sell', 'holdings', 'wallet', 'sig', 'jewellery', 'redemption', 'gold', 'silver',
            ])],
            'metal_type' => ['nullable', 'string', Rule::in(['gold', 'silver'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $activity->listTransactions(
            $request->user(),
            $data['filter'] ?? 'all',
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 20),
            $data['metal_type'] ?? null,
        );

        // List lives directly under data (not data.transactions).
        return ApiResponse::successList(
            $result['transactions'],
            '',
            200,
            [
                'filter' => $result['filter'] ?? ($data['filter'] ?? 'all'),
                'metal_type' => $result['metal_type'] ?? null,
                'pagination' => $result['pagination'] ?? [],
            ],
        );
    }

    public function show(Request $request, string $transaction, AccountActivityService $activity): JsonResponse
    {
        $payload = $activity->findTransaction($request->user(), $transaction);

        if ($payload === null) {
            throw ValidationException::withMessages([
                'transaction' => ['Transaction not found.'],
            ])->status(404);
        }

        // Detail object lives directly under data (not data.transaction).
        return ApiResponse::success($payload);
    }
}
