<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KjStore extends Model
{
    use HasFactory;

    protected $table = 'kj_stores';

    protected $guarded = [];

    public function KjStore()
    {
        return $this->hasMany(KjStore::class);
    }

    public function kjStores()
    {
        return $this->hasMany(KjStore::class);
    }

    public function kjStates(): belongsTo
    {
        return $this->belongsTo(AdminHoldConfig::class);
    }

    public function state()
    {
        return $this->belongsTo(KjState::class, 'kj_state_id');
    }


    public function exoConfig()
    {
        return $this->hasMany(KjExoPhoneConfig::class, 'kj_store_id');
    }

}
