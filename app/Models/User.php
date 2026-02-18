<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

/**
 * Class User
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */


// class User extends Model
class User extends Authenticatable
{
	// protected $table = 'adm_users_view';

	// protected $connection = 'pgsqlLaravelDB';

	protected $table = 'ml_user_v2';

	protected $connection = "mlms";

	protected $primaryKey = "ml_user_id";

	public $timestamps = false;

	public $incrementing = true;

	protected $keyType = 'int';


	use HasApiTokens, HasFactory, Notifiable;


	protected $casts = [
		'campaign_id' => 'array', // ✅ ensures JSON <-> array conversion
	];


	protected $hidden = [
		'password',
		'remember_token'
	];


	protected $guarded = [];

	// public function admRole() {
	// 	return $this->belongsTo(AdmRole::class, 'adm_roles_id', 'adm_roles_id');
	// }

	// public function admUser(){
	// 	return $this->hasOne(AdmUser::class, 'adm_users_id', 'adm_users_id');
	// }

	// public function isAdmin(){
	// 	return $this->admUser->adm_role->name == 'Admin' ? true : false;
	// }

	// public function employment(): BelongsTo {
	//     return $this->belongsTo(Employment::class, 'adm_users_id', 'adm_users_id');
	// }

	public function mlRole(): BelongsTo
	{
		return $this->belongsTo(MlUserRole::class, 'ml_role_id');
	}

	public function campaign(): BelongsTo
	{
		return $this->belongsTo(CampaignConfig::class, 'campaign_id');
	}

	public function mlRoleCheck(): BelongsTo
	{
		return $this->belongsTo(MlUserRole::class, 'ml_role_id');
	}


	public function manager(): BelongsTo
	{
		return $this->belongsTo(User::class, 'manager_id', 'ml_user_id')
			->whereHas('mlRole', function ($query) {
				$query->where('name', 'Manager');
			});
	}

	public function store()
	{
		return $this->belongsTo(KjStore::class, 'kj_store_id');
	}

	public function kjExoPhoneConfigs()
	{
		return $this->hasMany(KjExoPhoneConfig::class, 'kj_store_id', 'kj_store_id');
	}

	protected static function booted()
	{
		static::saving(function ($user) {
			$user->username = $user->email;
			// $user->original_password = Crypt::decrypt($user->password);
		});
	}

	public function role()
	{
		return $this->belongsTo(MlUserRole::class, 'ml_role_id');
	}

}
