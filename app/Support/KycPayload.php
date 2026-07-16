<?php

namespace App\Support;

use App\Models\KycDetail;
use App\Models\User;
use App\Services\KycService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KycPayload
{
    public static function overview(User $user, KycDetail $detail): array
    {
        $steps = self::steps($detail);
        $completed = collect($steps)->where('completed', true)->count();
        $progress = (int) round(($completed / max(count($steps), 1)) * 100);

        return [
            'title' => config('kyc.title', 'Identity Vault'),
            'kyc_status' => $user->kyc_status,
            'kyc_status_label' => self::userKycStatusLabel($user->kyc_status),
            'progress_percent' => $progress,
            'progress_label' => $progress.'%',
            'is_completed' => $user->kyc_status === 'approved',
            'can_submit' => self::canSubmit($detail, $user),
            'steps' => $steps,
            'submitted_at' => $detail->submitted_at?->toIso8601String(),
            'reviewed_at' => $detail->reviewed_at?->toIso8601String(),
            'rejection_reason' => $detail->rejection_reason,
        ];
    }

    public static function detail(KycDetail $detail): array
    {
        return [
            'full_name' => $detail->full_name,
            'pan_number' => self::maskPan($detail->pan_number),
            'pan_number_masked' => self::maskPan($detail->pan_number),
            'aadhaar_number' => self::maskAadhaar($detail->aadhaar_number),
            'aadhaar_number_masked' => self::maskAadhaar($detail->aadhaar_number),
            'bank_name' => $detail->bank_name,
            'account_holder_name' => $detail->account_holder_name,
            'account_number_masked' => self::maskAccount($detail->account_number),
            'ifsc_code' => $detail->ifsc_code,
            'upi_id' => $detail->upi_id,
            'face_photo_url' => AssetUrl::publicStorage($detail->selfie_photo),
            'pan_verification_status' => $detail->pan_verification_status,
            'aadhaar_verification_status' => $detail->aadhaar_verification_status,
            'digilocker_client_id' => $detail->digilocker_client_id,
            'face_verification_status' => $detail->face_verification_status,
            'bank_verification_status' => $detail->bank_verification_status,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function steps(KycDetail $detail): array
    {
        $definitions = config('kyc.steps', []);

        return collect($definitions)
            ->map(function (array $definition) use ($detail): array {
                $key = $definition['key'];
                $status = self::stepStatus($detail, $key);

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'provider_label' => $definition['provider_label'] ?? null,
                    'status' => $status,
                    'status_label' => self::stepStatusLabel($status),
                    'completed' => self::stepCompleted($detail, $key),
                    'action_required' => $status === 'action_required',
                ];
            })
            ->values()
            ->all();
    }

    public static function stepStatus(KycDetail $detail, string $step): string
    {
        return match ($step) {
            'pan' => (string) ($detail->pan_verification_status ?: 'action_required'),
            'aadhaar' => (string) ($detail->aadhaar_verification_status ?: 'action_required'),
            'face' => match ($detail->face_verification_status) {
                'approved' => 'verified',
                'rejected' => 'rejected',
                default => filled($detail->selfie_photo) ? 'submitted' : 'action_required',
            },
            'bank' => match ($detail->bank_verification_status) {
                'verified', 'approved' => 'verified',
                'rejected' => 'rejected',
                default => filled($detail->account_number) ? 'pending' : 'action_required',
            },
            default => 'action_required',
        };
    }

    public static function stepCompleted(KycDetail $detail, string $step): bool
    {
        return match ($step) {
            'pan' => $detail->pan_verification_status === 'verified',
            'aadhaar' => $detail->aadhaar_verification_status === 'verified',
            'face' => in_array($detail->face_verification_status, ['approved'], true)
                || filled($detail->selfie_photo),
            'bank' => filled($detail->account_number)
                && filled($detail->ifsc_code)
                && in_array($detail->bank_verification_status, ['pending', 'verified', 'approved'], true),
            default => false,
        };
    }

    public static function canSubmit(KycDetail $detail, User $user): bool
    {
        if (in_array($user->kyc_status, ['approved', 'submitted', 'under_review'], true)) {
            return false;
        }

        return self::stepCompleted($detail, 'pan')
            && self::stepCompleted($detail, 'aadhaar')
            && self::stepCompleted($detail, 'face')
            && self::stepCompleted($detail, 'bank');
    }

    /**
     * Surepass PAN + Aadhaar + bank verified — eligible for automatic KYC approval.
     */
    public static function isSurepassPanBankVerified(KycDetail $detail): bool
    {
        if (config('kyc.provider') !== 'surepass') {
            return false;
        }

        return $detail->pan_verification_status === 'verified'
            && $detail->aadhaar_verification_status === 'verified'
            && $detail->bank_verification_status === 'verified';
    }

    public static function canPerformTransactions(User $user, ?KycDetail $detail = null): bool
    {
        if ($user->kyc_status === 'approved') {
            return true;
        }

        $detail ??= $user->kycDetail;

        if (config('kyc.provider') === 'surepass' && $detail) {
            return self::isSurepassPanBankVerified($detail);
        }

        return false;
    }

    public static function transactionBlockedMessage(): string
    {
        return 'Complete KYC verification (PAN, Aadhaar, and bank account) before proceeding.';
    }

    public static function assertCanPerformTransactions(User $user): void
    {
        $user->loadMissing('kycDetail');

        if (self::canPerformTransactions($user, $user->kycDetail)) {
            return;
        }

        if ($user->kycDetail
            && config('kyc.provider') === 'surepass'
            && self::isSurepassPanBankVerified($user->kycDetail)) {
            app(KycService::class)->syncUserKycStatus($user, $user->kycDetail);
            $user->refresh()->load('kycDetail');

            if (self::canPerformTransactions($user, $user->kycDetail)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'kyc' => [self::transactionBlockedMessage()],
        ]);
    }

    public static function requiresAdminKycApproval(KycDetail $detail, User $user): bool
    {
        if ($user->kyc_status === 'approved') {
            return false;
        }

        return ! self::isSurepassPanBankVerified($detail);
    }

    public static function stepStatusLabel(?string $status): string
    {
        $normalized = match ($status) {
            'otp_sent', 'submitted' => 'pending',
            default => $status,
        };

        return config('kyc.step_statuses.'.$normalized, Str::headline(str_replace('_', ' ', (string) $status)));
    }

    public static function userKycStatusLabel(?string $status): string
    {
        return config('kyc.user_kyc_statuses.'.$status, Str::headline(str_replace('_', ' ', (string) $status)));
    }

    public static function maskPan(?string $pan): ?string
    {
        if (blank($pan) || strlen($pan) < 10) {
            return $pan;
        }

        return substr($pan, 0, 2).'XXXXX'.substr($pan, -3);
    }

    public static function maskAadhaar(?string $aadhaar): ?string
    {
        if (blank($aadhaar) || strlen($aadhaar) < 12) {
            return $aadhaar;
        }

        return 'XXXX XXXX '.substr($aadhaar, -4);
    }

    public static function maskAccount(?string $account): ?string
    {
        if (blank($account) || strlen($account) < 4) {
            return $account;
        }

        return str_repeat('•', max(4, strlen($account) - 4)).substr($account, -4);
    }
}
