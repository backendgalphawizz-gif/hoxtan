<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_blocked',
        'is_verified',
        'kyc_status',
        'gold_holdings',
        'silver_holdings',
        'wallet_balance',
        'blocked_at',
        'block_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
            'is_verified' => 'boolean',
            'gold_holdings' => 'decimal:4',
            'silver_holdings' => 'decimal:4',
            'wallet_balance' => 'decimal:2',
            'blocked_at' => 'datetime',
        ];
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

    public function isInvestor(): bool
    {
        return $this->role === 'investor' || $this->gold_holdings > 0 || $this->silver_holdings > 0;
    }
}
