<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_CARD = 'card';
    public const PAYMENT_TRANSFER = 'transfer';
    public const PAYMENT_MIXED = 'mixed';

    /** Tender methods that can appear on sale_payments rows. */
    public const PAYMENT_METHODS = [
        self::PAYMENT_CASH,
        self::PAYMENT_CARD,
        self::PAYMENT_TRANSFER,
    ];

    /** Values allowed on sales.payment_method (includes mixed summary). */
    public const PAYMENT_METHOD_LABELS = [
        self::PAYMENT_CASH,
        self::PAYMENT_CARD,
        self::PAYMENT_TRANSFER,
        self::PAYMENT_MIXED,
    ];

    protected $fillable = [
        'place_id',
        'station_id',
        'user_id',
        'customer_id',
        'register_session_id',
        'sold_at',
        'subtotal',
        'discount_amount',
        'discount_percent',
        'tax_base',
        'tax_total',
        'total',
        'cost_total',
        'status',
        'payment_method',
        'amount_received',
        'change_amount',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal' => 'float',
        'discount_amount' => 'float',
        'discount_percent' => 'float',
        'tax_base' => 'float',
        'tax_total' => 'float',
        'total' => 'float',
        'cost_total' => 'float',
        'amount_received' => 'float',
        'change_amount' => 'float',
    ];

    protected $appends = ['profit'];

    protected $with = ['lines', 'payments'];

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function getProfitAttribute(): float
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return 0.0;
        }

        return round(((float) $this->total) - ((float) $this->cost_total), 2);
    }
}
