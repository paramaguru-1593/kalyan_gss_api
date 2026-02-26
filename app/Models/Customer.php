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
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'nominee_dob' => 'date',
        'name_match_percentage' => 'decimal:2',
        'total_enrollments' => 'integer',
    ];

    /**
     * Enrollments for getSchemesByMobileNumber (one customer, many enrollments).
     */
    public function schemeEnrollments(): HasMany
    {
        return $this->hasMany(SchemeEnrollment::class);
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
