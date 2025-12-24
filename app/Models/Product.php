<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = ['brand_id', 'code', 'name', 'product_token_hash'];
    protected $hidden = ['product_token_hash'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
