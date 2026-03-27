<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorksheetItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'worksheet_id',
        'billable_item_id',
        'item_name_at_time',
        'price_at_time',
        'quantity',
    ];

    public function worksheet(): BelongsTo
    {
        return $this->belongsTo(Worksheet::class);
    }

    public function billableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class);
    }

    public function getLineTotalAttribute(): int
    {
        return $this->price_at_time * $this->quantity;
    }
}
