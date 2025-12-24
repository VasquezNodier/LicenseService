<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = ['name', 'api_key_hash', 'role'];
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function licenseKeys(): HasMany
    {
        return $this->hasMany(LicenseKey::class);
    }

}
