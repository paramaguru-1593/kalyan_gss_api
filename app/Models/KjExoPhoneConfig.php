<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KjExoPhoneConfig extends Model
{
    use HasFactory;

    protected $table = 'kj_exo_phone_configs';

    protected $guarded = [];

    public function store()
    {
        return $this->belongsTo(KjStore::class, 'kj_store_id');
    }

    protected static function booted()
    {
        static::saving(function ($model) {
            if ($model->status === 'Inactive' && empty($model->exotel_end_date)) {
                $model->exotel_end_date = now();
            }

            if ($model->status === 'Active') {
                $model->exotel_start_date = now();
                $model->exotel_end_date = null;
            }
        });
    }

}
