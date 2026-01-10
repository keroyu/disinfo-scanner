<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'event_type',
        'product_id',
        'portaly_product_id',
        'customer_email',
        'customer_name',
        'user_id',
        'amount',
        'currency',
        'net_total',
        'payment_method',
        'status',
        'raw_payload',
        'trace_id',
        'error_message',
        'processed_at',
        'created_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'amount' => 'integer',
        'net_total' => 'integer',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Status constants.
     */
    public const STATUS_SUCCESS = 'success';
    public const STATUS_USER_NOT_FOUND = 'user_not_found';
    public const STATUS_PRODUCT_NOT_FOUND = 'product_not_found';
    public const STATUS_PRODUCT_INACTIVE = 'product_inactive';
    public const STATUS_SIGNATURE_INVALID = 'signature_invalid';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_REFUND = 'refund';
    public const STATUS_SETTINGS_NOT_CONFIGURED = 'settings_not_configured';

    /**
     * Get the product associated with this log.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PaymentProduct::class, 'product_id');
    }

    /**
     * Get the user associated with this log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if order already exists (for idempotency).
     */
    public static function orderExists(string $orderId): bool
    {
        return static::where('order_id', $orderId)->exists();
    }

    /**
     * Get formatted amount with currency.
     */
    public function getFormattedAmountAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->amount);
    }

    /**
     * Get status badge class for UI.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'bg-green-100 text-green-800',
            self::STATUS_USER_NOT_FOUND => 'bg-yellow-100 text-yellow-800',
            self::STATUS_PRODUCT_NOT_FOUND, self::STATUS_PRODUCT_INACTIVE => 'bg-orange-100 text-orange-800',
            self::STATUS_SIGNATURE_INVALID, self::STATUS_SETTINGS_NOT_CONFIGURED => 'bg-red-100 text-red-800',
            self::STATUS_DUPLICATE => 'bg-gray-100 text-gray-800',
            self::STATUS_REFUND => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get status label in Traditional Chinese.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => '成功',
            self::STATUS_USER_NOT_FOUND => '用戶未找到',
            self::STATUS_PRODUCT_NOT_FOUND => '商品未找到',
            self::STATUS_PRODUCT_INACTIVE => '商品已停用',
            self::STATUS_SIGNATURE_INVALID => '簽名無效',
            self::STATUS_DUPLICATE => '重複訂單',
            self::STATUS_REFUND => '退款',
            self::STATUS_SETTINGS_NOT_CONFIGURED => '設定未配置',
            default => $this->status,
        };
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by customer email.
     */
    public function scopeEmail($query, string $email)
    {
        return $query->where('customer_email', 'like', '%' . $email . '%');
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where('created_at', '>=', $from . ' 00:00:00');
        }
        if ($to) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        return $query;
    }
}
