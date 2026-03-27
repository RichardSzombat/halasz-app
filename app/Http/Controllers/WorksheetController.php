<?php

namespace App\Http\Controllers;

use App\Exports\WorksheetsExport;
use App\Http\Requests\StoreWorksheetRequest;
use App\Http\Requests\UpdateWorksheetRequest;
use App\Models\BillableItem;
use App\Models\Worksheet;
use App\Models\WorksheetItem;
use App\Services\WorksheetEarningsCalculator;
use App\Support\BillableItemCatalog;
use App\Support\BillableItemTagPalette;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WorksheetController extends Controller
{
    public function __construct(
        private readonly WorksheetEarningsCalculator $calculator,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $this->extractFilters($request);
        $sort = $this->resolveSort($request->string('sort')->toString());
        $today = now()->toDateString();

        $worksheets = $this->fetchWorksheets($filters, $sort);

        return view('worksheets.index', [
            'worksheets' => $worksheets,
            'rangeTotal' => $filters['from'] || $filters['to']
                ? $this->calculator->calculateRangeTotal($filters['from'], $filters['to'])
                : $this->calculator->calculateMonthlyTotal(),
            'dailyTotal' => $this->calculator->calculateDailyTotal(),
            'activeRangeLabel' => $this->buildRangeLabel($filters['from'], $filters['to']),
            'filters' => $filters,
            'filterInputs' => [
                'from' => $filters['from_display'] ?? $today,
                'to' => $filters['to_display'] ?? $today,
            ],
            'sortKey' => $request->string('sort')->toString(),
            'tagPalette' => BillableItemTagPalette::mapForNames($this->collectItemNames($worksheets)),
            'todayDate' => $today,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $this->extractFilters($request);
        $sort = $this->resolveSort($request->string('sort')->toString());
        $worksheets = $this->fetchWorksheets($filters, $sort);
        $rangeTotal = $filters['from'] || $filters['to']
            ? $this->calculator->calculateRangeTotal($filters['from'], $filters['to'])
            : $this->calculator->calculateMonthlyTotal();

        return Excel::download(
            new WorksheetsExport($worksheets, $rangeTotal),
            $this->buildExportFilename($filters['from'], $filters['to']),
            ExcelFormat::XLS
        );
    }

    public function create(Request $request): View
    {
        return view('worksheets.create', $this->formViewData(new Worksheet(), 'create', $request));
    }

    public function store(StoreWorksheetRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $worksheet = Worksheet::create($request->safe()->only([
                'worksheet_number',
                'work_date',
                'note',
            ]));

            $this->syncWorksheetItems($worksheet, collect($request->validated('items')));
        });

        return redirect($this->resolveRedirectTarget($request))
            ->with('status', 'A munkalap sikeresen létrejött.');
    }

    public function show(Worksheet $worksheet): RedirectResponse
    {
        return redirect()->route('worksheets.edit', $worksheet);
    }

    public function edit(Request $request, Worksheet $worksheet): View
    {
        $worksheet->load(['items.billableItem']);

        return view('worksheets.edit', $this->formViewData($worksheet, 'edit', $request));
    }

    public function update(UpdateWorksheetRequest $request, Worksheet $worksheet): RedirectResponse
    {
        DB::transaction(function () use ($request, $worksheet): void {
            $worksheet->update($request->safe()->only([
                'worksheet_number',
                'work_date',
                'note',
            ]));

            $this->syncWorksheetItems($worksheet, collect($request->validated('items')));
        });

        return redirect($this->resolveRedirectTarget($request))
            ->with('status', 'A munkalap sikeresen frissült.');
    }

    public function destroy(Request $request, Worksheet $worksheet): RedirectResponse
    {
        $worksheet->delete();

        return redirect($this->resolveRedirectTarget($request))
            ->with('status', 'A munkalap törölve lett.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'worksheet_ids' => ['required', 'array', 'min:1'],
            'worksheet_ids.*' => ['integer', 'exists:worksheets,id'],
        ]);

        Worksheet::query()
            ->whereIn('id', $validated['worksheet_ids'])
            ->delete();

        return redirect()
            ->route('worksheets.index', $request->only(['from', 'to', 'sort']))
            ->with('status', 'A kijelölt munkalapok törölve lettek.');
    }

    public function reset(): RedirectResponse
    {
        return redirect()->route('worksheets.index');
    }

    private function formViewData(Worksheet $worksheet, string $mode, Request $request): array
    {
        BillableItemCatalog::ensureSeeded();

        $worksheet->loadMissing(['items.billableItem']);
        $billableItems = BillableItem::query()->orderBy('name')->get();
        $selectedItems = $worksheet->items
            ->map(fn (WorksheetItem $item) => [
                'worksheet_item_id' => $item->id,
                'billable_item_id' => $item->billable_item_id,
                'quantity' => 1,
                'snapshot_name' => $item->item_name_at_time,
                'snapshot_price' => $item->price_at_time,
            ])
            ->values()
            ->all();

        return [
            'worksheet' => $worksheet,
            'billableItems' => $billableItems,
            'selectedItems' => old('items', $selectedItems),
            'mode' => $mode,
            'workDateInput' => old(
                'work_date',
                $worksheet->work_date?->toDateString() ?? now()->toDateString()
            ),
            'redirectTo' => old('redirect_to', $request->query('redirect_to', url()->previous())),
            'todayDate' => now()->toDateString(),
        ];
    }

    private function syncWorksheetItems(Worksheet $worksheet, Collection $items): void
    {
        $existingItems = $worksheet->items()->get()->keyBy('billable_item_id');
        $selectedBillableIds = [];

        foreach ($items as $itemData) {
            if (! is_array($itemData)) {
                continue;
            }

            $billableItemId = (int) $itemData['billable_item_id'];
            $selectedBillableIds[] = $billableItemId;
            $existingItem = $existingItems->get($billableItemId);

            if ($existingItem instanceof WorksheetItem) {
                $existingItem->update([
                    'quantity' => 1,
                ]);

                continue;
            }

            $billableItem = BillableItem::query()->findOrFail($billableItemId);

            $worksheet->items()->create([
                'billable_item_id' => $billableItem->id,
                'item_name_at_time' => $billableItem->name,
                'price_at_time' => $billableItem->price,
                'quantity' => 1,
            ]);
        }

        $deleteQuery = $worksheet->items();

        if ($selectedBillableIds !== []) {
            $deleteQuery->whereNotIn('billable_item_id', array_unique($selectedBillableIds));
        }

        $deleteQuery->delete();
    }

    private function resolveRedirectTarget(Request $request): string
    {
        $target = $request->string('redirect_to')->toString();

        if ($target !== '' && str_starts_with($target, url('/'))) {
            return $target;
        }

        return route('worksheets.index');
    }

    private function extractFilters(Request $request): array
    {
        $fromInput = $request->string('from')->trim()->toString();
        $toInput = $request->string('to')->trim()->toString();
        $from = $this->parseDateInput($fromInput);
        $to = $this->parseDateInput($toInput);

        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to, $from];
            [$fromInput, $toInput] = [$toInput, $fromInput];
        }

        return [
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'from_display' => $fromInput !== '' ? $fromInput : null,
            'to_display' => $toInput !== '' ? $toInput : null,
        ];
    }

    private function parseDateInput(?string $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value);
        }

        if (preg_match('/^\d{4}\.\d{2}\.\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y.m.d', $value);
        }

        return null;
    }

    private function resolveSort(string $sortKey): array
    {
        return match ($sortKey) {
            'name_asc' => ['worksheet_number', 'asc'],
            'name_desc' => ['worksheet_number', 'desc'],
            'date_asc' => ['work_date', 'asc'],
            default => ['work_date', 'desc'],
        };
    }

    private function buildRangeLabel(?string $from, ?string $to): string
    {
        if (! $from && ! $to) {
            return 'Aktuális havi bevétel';
        }

        $start = $from ? Carbon::parse($from)->format('Y. m. d.') : 'kezdettől';
        $end = $to ? Carbon::parse($to)->format('Y. m. d.') : 'ma';

        return "{$start} - {$end}";
    }

    private function collectItemNames(Collection $worksheets): array
    {
        return $worksheets
            ->flatMap(fn (Worksheet $worksheet) => $worksheet->items->pluck('item_name_at_time'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function fetchWorksheets(array $filters, array $sort): Collection
    {
        return Worksheet::query()
            ->with(['items.billableItem'])
            ->when($filters['from'], fn ($query, $from) => $query->whereDate('work_date', '>=', $from))
            ->when($filters['to'], fn ($query, $to) => $query->whereDate('work_date', '<=', $to))
            ->orderBy(...$sort)
            ->orderByDesc('id')
            ->get()
            ->each(function (Worksheet $worksheet): void {
                $worksheet->setAttribute('calculated_total', $this->calculator->calculateWorksheetTotal($worksheet));
            });
    }

    private function buildExportFilename(?string $from, ?string $to): string
    {
        if (! $from && ! $to) {
            $today = now()->toDateString();

            return "Elszamolas_{$today}_{$today}.xls";
        }

        $start = $from ?? $to ?? now()->toDateString();
        $end = $to ?? $from ?? now()->toDateString();

        return "Elszamolas_{$start}_{$end}.xls";
    }
}
