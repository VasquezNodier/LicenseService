<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $fillable = ['license_key_id','product_id','status','expires_at', 'max_seats'];
    protected $casts = ['expires_at' => 'datetime'];
    
    public function licenseKey(): BelongsTo
    {
        return $this->belongsTo(LicenseKey::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(Activation::class);
    }

    public function activeActivations(): HasMany
    {
        return $this->activations()->whereNull('revoked_at');
    }

    public function isValid(): bool {
        return $this->status === 'valid' && $this->expires_at->isFuture();
    }
}
