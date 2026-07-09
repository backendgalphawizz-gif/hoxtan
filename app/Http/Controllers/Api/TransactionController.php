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
            'filter' => ['nullable', 'string', Rule::in(['all', 'buy', 'sell', 'wallet', 'sig', 'jewellery', 'redemption'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return ApiResponse::success($activity->listTransactions(
            $request->user(),
            $data['filter'] ?? 'all',
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 20),
        ));
    }

    public function show(Request $request, string $transaction, AccountActivityService $activity): JsonResponse
    {
        $payload = $activity->findTransaction($request->user(), $transaction);

        if ($payload === null) {
            throw ValidationException::withMessages([
                'transaction' => ['Transaction not found.'],
            ])->status(404);
        }

        return ApiResponse::success([
            'transaction' => $payload,
        ]);
    }
}
