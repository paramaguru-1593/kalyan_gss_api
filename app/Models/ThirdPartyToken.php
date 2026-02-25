<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores access token and expiry for a named third-party API (e.g. MyKalyan).
 * Single row per "name"; token is refreshed when expired or within buffer window.
 */
class ThirdPartyToken extends Model
{
    protected $table = 'third_party_tokens';

    protected $fillable = [
        'name',
        'access_token',
        'expires_at',
        'user_id',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'user_id' => 'integer',
    ];
}
