<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $fillable = [
        'sale_id',
        'place_id',
        'station_id',
        'user_id',
        'register_session_id',
        'returned_at',
        'reason',
        'subtotal',
        'discount_amount',
        'tax_base',
        'tax_total',
        'total',
        'cost_total',
        'dian_credit_note_id',
        'dian_status',
    ];

    protected $casts = [
        'returned_at' => 'datetime',
        'subtotal' => 'float',
        'discount_amount' => 'float',
        'tax_base' => 'float',
        'tax_total' => 'float',
        'total' => 'float',
        'cost_total' => 'float',
    ];

    protected $with = ['lines', 'payments'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleReturnLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SaleReturnPayment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
