<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'portaly_product_id',
        'portaly_url',
        'price',
        'currency',
        'duration_days',
        'action_type',
        'status',
    ];

    protected $casts = [
        'price' => 'integer',
        'duration_days' => 'integer',
    ];

    /**
     * Get the payment logs for this product.
     */
    public function paymentLogs(): HasMany
    {
        return $this->hasMany(PaymentLog::class, 'product_id');
    }

    /**
     * Scope to get only active products.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Check if the product is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get formatted price with currency.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'NT$ ' . number_format($this->price);
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        return $this->duration_days ? $this->duration_days . ' 天' : '-';
    }

    /**
     * Find active product by Portaly product ID.
     */
    public static function findActiveByPortalyId(string $portalyProductId): ?self
    {
        return static::active()
            ->where('portaly_product_id', $portalyProductId)
            ->first();
    }

    /**
     * Find product by Portaly product ID (including inactive).
     */
    public static function findByPortalyId(string $portalyProductId): ?self
    {
        return static::where('portaly_product_id', $portalyProductId)->first();
    }
}
