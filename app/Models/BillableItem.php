<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillableItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
    ];

    public function worksheetItems(): HasMany
    {
        return $this->hasMany(WorksheetItem::class);
    }
}
