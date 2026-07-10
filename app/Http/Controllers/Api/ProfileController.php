<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use App\Support\ApiResponse;
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

    public function update(Request $request): JsonResponse
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
            $updates['email'] = $user->phone.'@hoxtan.app';
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

        if ($updates === []) {
            throw ValidationException::withMessages([
                'message' => ['No profile fields were provided to update.'],
            ]);
        }

        $user->update($updates);

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

        return ApiResponse::success([
            'invoices' => $invoices,
        ]);
    }

    public function downloadInvoice(Request $request, Invoice $invoice, InvoiceService $invoices): StreamedResponse|JsonResponse
    {
        if ($invoice->user_id !== $request->user()->id) {
            return ApiResponse::error('Unauthorized.', [], 403);
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
