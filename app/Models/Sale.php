<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIALLY_RETURNED = 'partially_returned';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_CARD = 'card';
    public const PAYMENT_TRANSFER = 'transfer';
    public const PAYMENT_MIXED = 'mixed';

    public const DOCUMENT_NONE = 'none';
    public const DOCUMENT_POS_TICKET = 'pos_ticket';
    public const DOCUMENT_ELECTRONIC_INVOICE = 'electronic_invoice';

    public const DOCUMENT_TYPES = [
        self::DOCUMENT_NONE,
        self::DOCUMENT_POS_TICKET,
        self::DOCUMENT_ELECTRONIC_INVOICE,
    ];

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
        'returned_total',
        'returned_cost_total',
        'status',
        'document_type',
        'pos_number',
        'pos_prefix',
        'pos_consecutive',
        'pos_resolution_number',
        'pos_resolution_date',
        'pos_range_from',
        'pos_range_to',
        'fe_status',
        'fe_reference_code',
        'fe_number',
        'fe_cufe',
        'fe_qr_url',
        'fe_public_url',
        'fe_error',
        'fe_emitted_at',
        'nc_status',
        'nc_reference_code',
        'nc_number',
        'nc_cude',
        'nc_qr_url',
        'nc_public_url',
        'nc_error',
        'nc_emitted_at',
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
        'fe_emitted_at' => 'datetime',
        'nc_emitted_at' => 'datetime',
        'pos_resolution_date' => 'date',
        'pos_consecutive' => 'integer',
        'pos_range_from' => 'integer',
        'pos_range_to' => 'integer',
        'subtotal' => 'float',
        'discount_amount' => 'float',
        'discount_percent' => 'float',
        'tax_base' => 'float',
        'tax_total' => 'float',
        'total' => 'float',
        'cost_total' => 'float',
        'returned_total' => 'float',
        'returned_cost_total' => 'float',
        'amount_received' => 'float',
        'change_amount' => 'float',
    ];

    protected $appends = ['profit', 'net_total'];

    protected $with = ['lines', 'payments'];

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class)->orderByDesc('returned_at')->orderByDesc('id');
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

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function getNetTotalAttribute(): float
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return 0.0;
        }

        return round(((float) $this->total) - ((float) ($this->returned_total ?? 0)), 2);
    }

    public function getProfitAttribute(): float
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return 0.0;
        }

        $netSales = ((float) $this->total) - ((float) ($this->returned_total ?? 0));
        $netCost = ((float) $this->cost_total) - ((float) ($this->returned_cost_total ?? 0));

        return round($netSales - $netCost, 2);
    }
}
