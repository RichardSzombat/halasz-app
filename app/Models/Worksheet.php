<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worksheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'worksheet_number',
        'work_date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorksheetItem::class);
    }

    protected function totalAmount(): Attribute
    {
        return Attribute::get(function (): int {
            if ($this->relationLoaded('items')) {
                return (int) $this->items->sum(fn (WorksheetItem $item): int => $item->line_total);
            }

            return (int) $this->items()
                ->selectRaw('COALESCE(SUM(price_at_time * quantity), 0) as total')
                ->value('total');
        });
    }
}
