<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class borrow extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = ['user_id', 'product_id', 'borrow_days', 'borrow_product_number', 'borrow_status','return_date'];
    public function users()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    // public function users()
    // {
    //     return $this->hasOne(User::class);
    // }
    public function products()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
