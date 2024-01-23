<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
class user extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = ['name', 'gender', 'birthday', 'phone', 'email', 'times_of_borrow', 'picture'];
    public function borrows()
    {
        return $this->hasMany(Borrow::class);
    }
    // public function borrows()
    // {
    //     return $this->belongsTo(Borrow::class);
    // }
    public function getPictureUrlAttribute()
    {
        $picturePath = $this->attributes['picture'];
        return Storage::url($picturePath);
    }
}
