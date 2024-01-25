<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes, Notifiable, HasApiTokens;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'gender', 'birthday', 'phone', 'email', 'password', 'times_of_borrow', 'picture', 'role'];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
    public function borrows()
    {
        return $this->hasMany(Borrow::class);
    }

    public function getPictureUrlAttribute()
    {
        $picturePath = $this->attributes['picture'];
        $appUrl = Config::get('app.url');
        return $appUrl . Storage::url($picturePath);
    }

    const ADMIN_TYPE = 1;
    const DEFAULT_TYPE = 0;
    public function isAdmin()
    {
        return $this->type === self::ADMIN_TYPE;
    }
}
