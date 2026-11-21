<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnLineLot extends Model
{
    protected $fillable = [
        'sale_return_line_id',
        'sale_line_lot_id',
        'stock_lot_id',
        'qty_base',
        'unit_cost',
    ];

    protected $casts = [
        'qty_base' => 'float',
        'unit_cost' => 'float',
    ];

    public function saleReturnLine(): BelongsTo
    {
        return $this->belongsTo(SaleReturnLine::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }
}
