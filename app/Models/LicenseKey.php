<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseKey extends Model
{
    protected $fillable = ['brand_id', 'key', 'customer_email'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }
}
