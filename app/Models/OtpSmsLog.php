<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpSmsLog extends Model
{
    use HasFactory;

    protected $table = 'otp_sms_logs';

    protected $guarded = [];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'charges' => 'decimal:4',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function otpVerification(): BelongsTo
    {
        return $this->belongsTo(OtpVerification::class, 'otp_verification_id');
    }
}
