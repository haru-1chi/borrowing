<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category', 'status', 'full_stock', 'in_stock', 'description'];
    public function borrows()
    {
        return $this->belongsTo(borrow::class, 'product_id', 'id');
    }
}
