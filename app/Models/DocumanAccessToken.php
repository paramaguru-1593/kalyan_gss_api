<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Docman India API access token. One record per name; token is refreshed when
 * expired or within 5 minutes of expires_at. expires_at is always now + 1 day on refresh.
 */
class DocumanAccessToken extends Model
{
    protected $table = 'documan_access_tokens';

    protected $fillable = [
        'name',
        'access_token',
        'token_type',
        'expires_in',
        'user_name',
        'expires_at',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'expires_in' => 'integer',
    ];
}
