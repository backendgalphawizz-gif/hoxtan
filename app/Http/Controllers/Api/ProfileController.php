<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('referredBy:id,name,phone');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'referral_code' => $user->referral_code,
                'wallet_balance' => (float) $user->wallet_balance,
                'gold_holdings' => (float) $user->gold_holdings,
                'silver_holdings' => (float) $user->silver_holdings,
                'kyc_status' => $user->kyc_status,
                'referred_by' => $user->referredBy ? [
                    'name' => $user->referredBy->name,
                    'phone' => $user->referredBy->phone,
                ] : null,
                'nominee' => [
                    'name' => $user->nominee_name,
                    'relation' => $user->nominee_relation,
                    'phone' => $user->nominee_phone,
                    'date_of_birth' => $user->nominee_date_of_birth?->toDateString(),
                ],
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:32', 'regex:/^[A-Za-z\s]+$/'],
            'nominee_name' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z\s]+$/'],
            'nominee_relation' => ['nullable', 'string', 'max:50'],
            'nominee_phone' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'nominee_date_of_birth' => ['nullable', 'date', 'before:today'],
        ]);

        $user = $request->user();
        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'nominee' => [
                    'name' => $user->nominee_name,
                    'relation' => $user->nominee_relation,
                    'phone' => $user->nominee_phone,
                    'date_of_birth' => $user->nominee_date_of_birth?->toDateString(),
                ],
            ],
        ]);
    }

    public function updateMpin(Request $request): JsonResponse
    {
        $length = \App\Support\MpinRules::length();

        $data = $request->validate([
            'current_mpin' => ['required', 'string', "digits:{$length}", 'regex:/^\d+$/'],
            'mpin' => ['required', 'string', "digits:{$length}", 'regex:/^\d+$/', 'different:current_mpin'],
            'mpin_confirmation' => ['required', 'same:mpin'],
        ], \App\Support\MpinRules::validationMessages());

        $user = $request->user();

        if (! $user->verifyMpin($data['current_mpin'])) {
            return response()->json(['message' => 'Current MPIN is incorrect.'], 422);
        }

        $user->update(['mpin' => $data['mpin']]);

        return response()->json(['message' => 'MPIN updated successfully.']);
    }

    public function referralStats(Request $request): JsonResponse
    {
        $user = $request->user()->loadCount([
            'referralsMade as successful_referrals' => fn ($q) => $q->where('status', 'credited'),
        ]);

        $totalEarned = $user->referralsMade()
            ->where('status', 'credited')
            ->sum('bonus_amount');

        return response()->json([
            'referral_code' => $user->referral_code,
            'successful_referrals' => (int) $user->successful_referrals,
            'total_earned' => (float) $totalEarned,
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $invoices = $request->user()
            ->invoices()
            ->with('investment:id,reference_id,type')
            ->latest('issued_at')
            ->get()
            ->map(fn (Invoice $invoice) => [
                'invoice_number' => $invoice->invoice_number,
                'investment_reference' => $invoice->investment?->reference_id,
                'metal_type' => $invoice->metal_type,
                'quantity_grams' => (float) $invoice->quantity_grams,
                'total_amount' => (float) $invoice->total_amount,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
                'download_url' => route('api.invoices.download', $invoice),
            ]);

        return response()->json(['invoices' => $invoices]);
    }

    public function downloadInvoice(Request $request, Invoice $invoice, InvoiceService $invoices): StreamedResponse|JsonResponse
    {
        if ($invoice->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $invoice->file_path || ! Storage::disk('local')->exists($invoice->file_path)) {
            $invoices->generateForInvestment($invoice->investment()->firstOrFail());
            $invoice->refresh();
        }

        return Storage::disk('local')->download(
            $invoice->file_path,
            $invoice->invoice_number.'.html',
            ['Content-Type' => 'text/html'],
        );
    }
}
