<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MlUserRole extends Model
{
    use HasFactory;

    protected $table = "ml_role";

    protected $primaryKey = "ml_role_id";

    protected $connection = "mlms";

    protected $guarded = [];

}
