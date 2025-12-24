<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activation extends Model
{
    protected $fillable = ['license_id', 'instance_type', 'instance_identifier', 'activated_at', 'revoked_at'];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
