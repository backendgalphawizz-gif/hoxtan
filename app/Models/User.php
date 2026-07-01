<?php

namespace App\Models;

use App\Services\ReferralService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'mpin',
        'role',
        'is_blocked',
        'is_verified',
        'kyc_status',
        'gold_holdings',
        'silver_holdings',
        'wallet_balance',
        'blocked_at',
        'block_reason',
        'referral_code',
        'referred_by_id',
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
            'mpin' => 'hashed',
            'is_blocked' => 'boolean',
            'is_verified' => 'boolean',
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

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function investmentGoals(): HasMany
    {
        return $this->hasMany(InvestmentGoal::class);
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

    public function isInvestor(): bool
    {
        return $this->role === 'investor' || $this->gold_holdings > 0 || $this->silver_holdings > 0;
    }

    public function verifyMpin(string $mpin): bool
    {
        if (blank($this->mpin)) {
            return false;
        }

        return \Illuminate\Support\Facades\Hash::check($mpin, $this->mpin);
    }
}
