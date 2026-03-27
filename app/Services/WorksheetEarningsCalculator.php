<?php

namespace App\Services;

use App\Models\Worksheet;
use Carbon\Carbon;

class WorksheetEarningsCalculator
{
    public function calculateDailyTotal(): int
    {
        return (int) ($this->buildTotalQuery()
            ->whereDate('worksheets.work_date', now()->toDateString())
            ->value('total') ?? 0);
    }

    public function calculateMonthlyTotal(): int
    {
        return (int) ($this->buildTotalQuery()
            ->whereBetween('worksheets.work_date', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ])
            ->value('total') ?? 0);
    }

    public function calculateRangeTotal(null|string|Carbon $from, null|string|Carbon $to): int
    {
        $startDate = $from ? Carbon::parse($from)->toDateString() : null;
        $endDate = $to ? Carbon::parse($to)->toDateString() : null;

        return (int) ($this->buildTotalQuery()
            ->when($startDate, fn ($query, $startDate) => $query->whereDate('worksheets.work_date', '>=', $startDate))
            ->when($endDate, fn ($query, $endDate) => $query->whereDate('worksheets.work_date', '<=', $endDate))
            ->value('total') ?? 0);
    }

    public function calculateWorksheetTotal(Worksheet $worksheet): int
    {
        if ($worksheet->relationLoaded('items')) {
            return (int) $worksheet->items->sum(fn ($item): int => $item->line_total);
        }

        return (int) ($worksheet->items()
            ->selectRaw('COALESCE(SUM(price_at_time * quantity), 0) as total')
            ->value('total') ?? 0);
    }

    private function buildTotalQuery()
    {
        return Worksheet::query()
            ->leftJoin('worksheet_items', 'worksheet_items.worksheet_id', '=', 'worksheets.id')
            ->selectRaw('COALESCE(SUM(worksheet_items.price_at_time * worksheet_items.quantity), 0) as total');
    }
}
