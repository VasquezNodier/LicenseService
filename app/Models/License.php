<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function renew(CarbonInterface $expiresAt): void
    {
        // rule: not enable renew if is cancelled
        if ($this->status === 'cancelled') {
            // to test: 409 is clear
            abort(response()->json(['message' => 'Cannot renew a cancelled license'], 409));
        }

        $this->expires_at = $expiresAt;
        // optional: whether was suspended and then renew, ¿it becomes valid?
        // I'm NOT doing it automatic: renew != resume
        $this->save();
    }

    public function suspend(): void
    {
        if ($this->status === 'cancelled') return;
        if ($this->status === 'suspended') return;
        $this->status = 'suspended';
        $this->save();
    }

    public function resume(): void
    {
        if ($this->status !== 'suspended') return;
        $this->status = 'valid';
        $this->save();
    }

    public function cancel(): void
    {
        if ($this->status === 'cancelled') return;
        $this->status = 'cancelled';
        $this->save();
    }

    public function remainingSeats(): ?int
    {
        if ($this->max_seats === null) {
            return null;
        }

        $used = $this->activeActivations()->count();
        return max($this->max_seats - $used, 0);
    }
}
