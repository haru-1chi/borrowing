<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class product extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = ['name', 'category', 'status', 'full_stock', 'in_stock', 'description'];
    // public function Borrow()
    // {
    //     return $this->belongsTo(Borrow::class, 'product_id', 'id');
    // }
    public function borrows()
    {
        return $this->hasMany(Borrow::class);
    }
}
