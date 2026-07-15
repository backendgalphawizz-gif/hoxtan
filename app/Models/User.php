<?php

namespace App\Models;

use App\Services\ReferralService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'mpin',
        'role',
        'is_blocked',
        'is_employee',
        'employee_code',
        'is_verified',
        'kyc_status',
        'gold_holdings',
        'silver_holdings',
        'wallet_balance',
        'blocked_at',
        'block_reason',
        'referral_code',
        'referred_by_id',
        'gender',
        'date_of_birth',
        'primary_residence',
        'profile_photo',
        'market_alerts',
        'nominee_name',
        'nominee_relation',
        'nominee_phone',
        'nominee_date_of_birth',
    ];

    protected $hidden = [
        'password',
        'mpin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
            'is_employee' => 'boolean',
            'is_verified' => 'boolean',
            'market_alerts' => 'boolean',
            'date_of_birth' => 'date',
            'gold_holdings' => 'decimal:4',
            'silver_holdings' => 'decimal:4',
            'wallet_balance' => 'decimal:2',
            'blocked_at' => 'datetime',
            'nominee_date_of_birth' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->referral_code)) {
                $user->referral_code = ReferralService::generateUniqueCode();
            }

            if (blank($user->email) && filled($user->phone)) {
                $user->email = $user->phone.'@hoxtan.app';
            }
        });
    }

    public function kycDetail(): HasOne
    {
        return $this->hasOne(KycDetail::class);
    }

    public function restriction(): HasOne
    {
        return $this->hasOne(UserRestriction::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function investmentGoals(): HasMany
    {
        return $this->hasMany(InvestmentGoal::class);
    }

    public function sigPlans(): HasMany
    {
        return $this->hasMany(SigPlan::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'tokenable');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_id');
    }

    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referralReceived(): HasOne
    {
        return $this->hasOne(Referral::class, 'referee_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function holdingCertificates(): HasMany
    {
        return $this->hasMany(HoldingCertificate::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function jewelleryProductViews(): HasMany
    {
        return $this->hasMany(JewelleryProductView::class);
    }

    public function jewelleryOrders(): HasMany
    {
        return $this->hasMany(JewelleryOrder::class);
    }

    public function oldGoldBookings(): HasMany
    {
        return $this->hasMany(OldGoldBooking::class);
    }

    public function isInvestor(): bool
    {
        return $this->role === 'investor' || $this->gold_holdings > 0 || $this->silver_holdings > 0;
    }

    protected function mpin(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value,
            set: fn (?string $value): ?string => filled($value) ? $value : null,
        );
    }

    public function verifyMpin(string $mpin): bool
    {
        $raw = $this->getRawOriginal('mpin');

        if (blank($raw)) {
            return false;
        }

        if ($this->valueUsesLegacyHashedMpin($raw)) {
            if (! \Illuminate\Support\Facades\Hash::check($mpin, $raw)) {
                return false;
            }

            $this->forceFill(['mpin' => $mpin])->saveQuietly();

            return true;
        }

        if ($this->valueUsesEncryptedMpinStorage($raw)) {
            $stored = $this->readableMpin();

            if (blank($stored)) {
                return false;
            }

            if (! hash_equals($stored, $mpin)) {
                return false;
            }

            $this->forceFill(['mpin' => $mpin])->saveQuietly();

            return true;
        }

        return hash_equals((string) $raw, $mpin);
    }

    public function readableMpin(): ?string
    {
        $raw = $this->getRawOriginal('mpin');

        if (blank($raw)) {
            return null;
        }

        if ($this->valueUsesLegacyHashedMpin($raw)) {
            return null;
        }

        if ($this->valueUsesEncryptedMpinStorage($raw)) {
            try {
                return decrypt($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        return (string) $raw;
    }

    public function usesLegacyHashedMpin(): bool
    {
        return $this->valueUsesLegacyHashedMpin($this->getRawOriginal('mpin'));
    }

    public function usesEncryptedMpinStorage(): bool
    {
        return $this->valueUsesEncryptedMpinStorage($this->getRawOriginal('mpin'));
    }

    protected function valueUsesLegacyHashedMpin(mixed $value): bool
    {
        return is_string($value) && (str_starts_with($value, '$2y$') || str_starts_with($value, '$2a$'));
    }

    protected function valueUsesEncryptedMpinStorage(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, 'eyJpdiI6');
    }
}
