<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * End-customer (consumer) model.
 * Authenticatable via mobile_number; tokens stored in personal_access_tokens.
 * Has many scheme enrollments (getSchemesByMobileNumber); total_enrollments is cached count.
 */
class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'customers';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
        'latest_otp',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'nominee_dob' => 'date',
        'name_match_percentage' => 'decimal:2',
        'total_enrollments' => 'integer',
        'otp_expires_at' => 'datetime',
        'otp_last_sent_at' => 'datetime',
    ];

    /**
     * Automatically assign internal customerId and customer_code number series
     * when a new customer is inserted and those fields are not provided.
     */
    protected static function booted(): void
    {
        static::created(function (Customer $customer): void {
            $updates = [];

            if ($customer->customerId === null) {
                // Generate a large numeric series in a range unlikely to collide
                // with external IDs, and ensure uniqueness even if data already exists.
                $candidate = 90000000000000 + $customer->id;
                while (static::where('customerId', $candidate)->exists()) {
                    $candidate++;
                }
                $updates['customerId'] = $candidate;
            }

            if ($customer->customer_code === null) {
                // EQL + zero-padded sequence; also guard for uniqueness.
                $candidateCode = sprintf('EQL%08d', $customer->id);
                $suffix = $customer->id;
                while (static::where('customer_code', $candidateCode)->exists()) {
                    $suffix++;
                    $candidateCode = sprintf('EQL%08d', $suffix);
                }
                $updates['customer_code'] = $candidateCode;
            }

            if ($updates !== []) {
                $customer->forceFill($updates)->save();
            }
        });
    }

    /**
     * Enrollments for getSchemesByMobileNumber (one customer, many enrollments).
     */
    public function schemeEnrollments(): HasMany
    {
        return $this->hasMany(SchemeEnrollment::class);
    }

    /**
     * Ledger/scheme details from getCustomerLedgerReport (optional link by customer_id).
     */
    public function customerSchemeDetails(): HasMany
    {
        return $this->hasMany(CustomerSchemeDetail::class);
    }

    /**
     * OTP verification history.
     */
    public function otpVerifications(): HasMany
    {
        return $this->hasMany(OtpVerification::class);
    }

    /**
     * OTP SMS send logs.
     */
    public function otpSmsLogs(): HasMany
    {
        return $this->hasMany(OtpSmsLog::class);
    }

    /**
     * Recompute and persist total_enrollments from current enrollments count.
     */
    public function refreshTotalEnrollments(): int
    {
        $count = $this->schemeEnrollments()->count();
        $this->update(['total_enrollments' => $count]);

        return $count;
    }

    /**
     * Full name (first + last) when available. Maps to API "customer_name".
     */
    public function getFullNameAttribute(): ?string
    {
        $parts = array_filter([$this->first_name ?? '', $this->last_name ?? '']);
        return implode(' ', $parts) ?: null;
    }

    /**
     * Alias for API / docs: customer_name.
     */
    public function getCustomerNameAttribute(): ?string
    {
        return $this->full_name;
    }
}
