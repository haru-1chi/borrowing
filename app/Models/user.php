<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'gender', 'birthday', 'phone', 'email', 'times_of_borrow'];
    public function borrows()
    {
        return $this->belongsTo(borrow::class, 'user_id', 'id');
    }
}
