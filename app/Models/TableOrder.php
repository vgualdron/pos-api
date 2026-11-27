<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableOrder extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'place_id',
        'dining_table_id',
        'user_id',
        'status',
        'opened_at',
        'last_sent_at',
        'closed_at',
        'subtotal',
        'discount_amount',
        'discount_percent',
        'tax_base',
        'tax_total',
        'total',
        'sale_id',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'closed_at' => 'datetime',
        'subtotal' => 'float',
        'discount_amount' => 'float',
        'discount_percent' => 'float',
        'tax_base' => 'float',
        'tax_total' => 'float',
        'total' => 'float',
    ];

    protected $with = ['lines'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TableOrderLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return ($this->status ?? self::STATUS_OPEN) === self::STATUS_OPEN;
    }
}
