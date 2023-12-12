<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class borrow extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'product_id', 'borrow_days', 'borrow_product_number', 'borrow_status'];
    public function users()
    {
        return $this->hasOne(user::class);
    }
    public function products()
    {
        return $this->hasMany(product::class);
    }
}
