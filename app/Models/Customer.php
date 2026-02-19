<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * End-customer (consumer) model.
 * Authenticatable via mobile_number; tokens stored in personal_access_tokens.
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
    ];

    /**
     * Full name (first + last) when available.
     */
    public function getFullNameAttribute(): ?string
    {
        $parts = array_filter([$this->first_name ?? '', $this->last_name ?? '']);
        return implode(' ', $parts) ?: null;
    }
}
