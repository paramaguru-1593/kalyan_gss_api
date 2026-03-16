<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpVerification extends Model
{
    use HasFactory;

    protected $table = 'otp_verifications';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function otpSmsLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OtpSmsLog::class, 'otp_verification_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
