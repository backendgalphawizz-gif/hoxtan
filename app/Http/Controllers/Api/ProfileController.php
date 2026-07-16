<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HoldingCertificate;
use App\Models\Invoice;
use App\Models\User;
use App\Services\HoldingCertificateService;
use App\Services\InvoiceService;
use App\Services\KycService;
use App\Support\ApiResponse;
use App\Support\AssetsBalancePayload;
use App\Support\FilamentFormFields;
use App\Support\MpinRules;
use App\Support\ProfilePhotoStorage;
use App\Support\UserProfilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => UserProfilePayload::make($request->user()),
        ]);
    }

    public function assets(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'assets' => AssetsBalancePayload::make($request->user()->fresh()),
        ]);
    }

    public function update(Request $request, KycService $kyc): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', 'regex:/^[A-Za-z\s]+$/'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'primary_residence' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'market_alerts' => ['nullable', 'boolean'],
            'profile_photo' => ['nullable'],
            'image' => ['nullable'],
            'profile_image' => ['nullable'],
            'avatar' => ['nullable'],
            'photo' => ['nullable'],
            'profile_photo_base64' => ['nullable', 'string'],
            'profile_image_base64' => ['nullable', 'string'],
            'image_base64' => ['nullable', 'string'],
            'nominee' => ['nullable', 'array'],
            'nominee.name' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z\s]+$/'],
            'nominee.relation' => ['nullable', 'string', 'max:50'],
            'nominee.phone' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'nominee.date_of_birth' => ['nullable', 'date', 'before:today'],
            'nominee_name' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z\s]+$/'],
            'nominee_relation' => ['nullable', 'string', 'max:50'],
            'nominee_phone' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'nominee_date_of_birth' => ['nullable', 'date', 'before:today'],
            'account_holder_name' => ['required', 'string', 'max:100', 'regex:'.FilamentFormFields::NAME_REGEX],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'min:6', 'max:30', 'regex:/^\d+$/'],
            'ifsc_code' => ['required', 'string', 'size:11', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
        ], [
            'account_holder_name.regex' => 'Account holder name may only contain letters and spaces.',
            'account_number.regex' => 'Account number must contain digits only.',
            'ifsc_code.regex' => 'Invalid IFSC code format.',
        ]);

        $updates = collect($data)->only([
            'name',
            'email',
            'primary_residence',
            'gender',
            'date_of_birth',
            'market_alerts',
        ])->all();

        if (array_key_exists('email', $updates) && blank($updates['email'])) {
            $updates['email'] = null;
        }

        if ($photoPath = ProfilePhotoStorage::storeForUser($user, $request)) {
            $updates['profile_photo'] = $photoPath;
        }

        if ($request->has('nominee')) {
            $nominee = $data['nominee'] ?? [];
            $updates = array_merge($updates, array_filter([
                'nominee_name' => $nominee['name'] ?? null,
                'nominee_relation' => $nominee['relation'] ?? null,
                'nominee_phone' => $nominee['phone'] ?? null,
                'nominee_date_of_birth' => $nominee['date_of_birth'] ?? null,
            ], fn ($value) => $value !== null));
        }

        foreach (['nominee_name', 'nominee_relation', 'nominee_phone', 'nominee_date_of_birth'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if ($updates !== []) {
            $user->update($updates);
        }

        $detail = $kyc->getOrCreateDetail($user->fresh());
        $detail->update([
            'account_holder_name' => $data['account_holder_name'],
            'bank_name' => $data['bank_name'],
            'account_number' => $data['account_number'],
            'ifsc_code' => strtoupper($data['ifsc_code']),
            'bank_verification_status' => $detail->bank_verification_status === 'verified'
                ? 'verified'
                : 'pending',
            'bank_submitted_at' => now(),
        ]);

        return ApiResponse::success([
            'user' => UserProfilePayload::make($user->fresh()),
        ], 'Profile updated successfully.');
    }

    public function updatePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'profile_photo' => ['nullable'],
            'image' => ['nullable'],
            'profile_image' => ['nullable'],
            'avatar' => ['nullable'],
            'photo' => ['nullable'],
            'profile_photo_base64' => ['nullable', 'string'],
            'profile_image_base64' => ['nullable', 'string'],
            'image_base64' => ['nullable', 'string'],
        ]);

        $photoPath = ProfilePhotoStorage::storeForUser($user, $request);

        if (! $photoPath) {
            throw ValidationException::withMessages([
                'image' => ['Please upload a profile photo using the image field (multipart file or base64).'],
            ]);
        }

        $user->update([
            'profile_photo' => $photoPath,
        ]);

        return ApiResponse::success([
            'user' => UserProfilePayload::make($user->fresh()),
        ], 'Profile photo updated successfully.');
    }

    public function updateMpin(Request $request): JsonResponse
    {
        $length = MpinRules::length();

        $data = $request->validate([
            'current_mpin' => ['required', 'string', "digits:{$length}", 'regex:/^\d+$/'],
            'mpin' => ['required', 'string', "digits:{$length}", 'regex:/^\d+$/', 'different:current_mpin'],
        ], array_merge(MpinRules::validationMessages(), [
            'mpin.different' => 'New M-PIN must be different from your current M-PIN.',
        ]));

        $user = $request->user();

        if (! $user->verifyMpin($data['current_mpin'])) {
            throw ValidationException::withMessages([
                'current_mpin' => ['Current M-PIN is incorrect.'],
            ]);
        }

        $user->update(['mpin' => $data['mpin']]);

        return ApiResponse::success([
            'mpin' => $data['mpin'],
            'mpin_length' => $length,
        ], 'M-PIN updated successfully.');
    }

    public function destroy(Request $request): JsonResponse
    {
        return $this->closeAccount($request);
    }

    public function closeAccount(Request $request): JsonResponse
    {
        $length = MpinRules::length();

        $data = $request->validate([
            'mpin' => ['required', 'string', "digits:{$length}", 'regex:/^\d+$/'],
        ], MpinRules::validationMessages());

        /** @var User $user */
        $user = $request->user();

        if (! $user->verifyMpin($data['mpin'])) {
            throw ValidationException::withMessages([
                'mpin' => ['Invalid M-PIN. Account could not be closed.'],
            ]);
        }

        $user->tokens()->delete();

        if (filled($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $user->delete();

        return ApiResponse::success([
            'closed' => true,
            'message' => 'Your account has been closed successfully.',
        ], 'Your account has been closed successfully.');
    }

    public function referralStats(Request $request): JsonResponse
    {
        $user = $request->user()->loadCount([
            'referralsMade as successful_referrals' => fn ($q) => $q->where('status', 'credited'),
        ]);

        $totalEarned = $user->referralsMade()
            ->where('status', 'credited')
            ->sum('bonus_amount');

        return ApiResponse::success([
            'referral_code' => $user->referral_code,
            'successful_referrals' => (int) $user->successful_referrals,
            'total_earned' => (float) $totalEarned,
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $invoices = $request->user()
            ->invoices()
            ->with([
                'investment:id,reference_id,type',
                'jewelleryOrder:id,order_number',
                'oldGoldBooking:id,booking_number',
            ])
            ->latest('issued_at')
            ->get()
            ->map(fn (Invoice $invoice) => app(InvoiceService::class)->apiPayload($invoice));

        return ApiResponse::success([
            'invoices' => $invoices,
        ]);
    }

    public function downloadInvoice(Invoice $invoice, InvoiceService $invoices): StreamedResponse|JsonResponse
    {
        if (! $invoice->file_path || ! Storage::disk('local')->exists($invoice->file_path)) {
            if ($invoice->jewellery_order_id) {
                $order = $invoice->jewelleryOrder()->firstOrFail();
                $invoices->generateForJewelleryOrder($order);
            } elseif ($invoice->old_gold_booking_id) {
                $booking = $invoice->oldGoldBooking()->firstOrFail();
                $invoices->generateForOldGoldBooking($booking);
            } elseif ($invoice->investment_id) {
                $invoices->generateForInvestment($invoice->investment()->firstOrFail());
            }
            $invoice->refresh();
        }

        if (! $invoice->file_path || ! Storage::disk('local')->exists($invoice->file_path)) {
            return ApiResponse::error('Invoice file not found.', [], 404);
        }

        return Storage::disk('local')->download(
            $invoice->file_path,
            $invoice->invoice_number.'.html',
            ['Content-Type' => 'text/html'],
        );
    }

    public function downloadCertificate(
        HoldingCertificate $certificate,
        HoldingCertificateService $certificates,
    ): StreamedResponse|JsonResponse {
        $investment = $certificate->investment()->with('user')->firstOrFail();
        $certificates->writeFile($certificate, $investment, $investment->user);
        $certificate->refresh();

        if (! $certificate->file_path || ! Storage::disk('local')->exists($certificate->file_path)) {
            return ApiResponse::error('Certificate file not found.', [], 404);
        }

        return Storage::disk('local')->download(
            $certificate->file_path,
            $certificate->certificate_number.'.html',
            ['Content-Type' => 'text/html'],
        );
    }
}
