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
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WorksheetController extends Controller
{
    public function __construct(
        private readonly WorksheetEarningsCalculator $calculator,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->extractFilters($request);
        $sort = $this->resolveSort($request->string('sort')->toString());
        $today = now()->toDateString();

        $worksheets = $this->fetchWorksheets($filters, $sort);

        return view('worksheets.index', [
            'worksheets' => $worksheets,
            'rangeTotal' => $filters['has_active_filter']
                ? $this->calculator->calculateRangeTotal($filters['from'], $filters['to'])
                : $this->calculator->calculateMonthlyTotal(),
            'dailyTotal' => $this->calculator->calculateDailyTotal(),
            'activeRangeLabel' => $this->buildRangeLabel(
                $filters['has_active_filter'] ? $filters['from'] : null,
                $filters['has_active_filter'] ? $filters['to'] : null,
            ),
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
            new WorksheetsExport(
                $worksheets,
                $rangeTotal,
                (string) $request->user()?->name,
                $this->buildRangeLabel($filters['from'], $filters['to'])
            ),
            $this->buildExportFilename($filters['from'], $filters['to']),
            ExcelFormat::XLS
        );
    }

    public function create(Request $request): View
    {
        return view('worksheets.create', $this->formViewData(new Worksheet, 'create', $request));
    }

    public function store(StoreWorksheetRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $worksheet = Worksheet::create([
                ...$request->safe()->only([
                    'worksheet_number',
                    'work_date',
                    'note',
                ]),
                'user_id' => $request->user()->id,
            ]);

            $this->syncWorksheetItems($worksheet, collect($request->validated('items')));
        });

        return redirect($this->resolveRedirectTarget($request))
            ->with('status', 'Munkalap létrehozva.');
    }

    public function show(Worksheet $worksheet): RedirectResponse
    {
        $this->ensureOwnedByCurrentUser($worksheet);

        return redirect()->route('worksheets.edit', $worksheet);
    }

    public function edit(Request $request, Worksheet $worksheet): View
    {
        $this->ensureOwnedByCurrentUser($worksheet);
        $worksheet->load(['items.billableItem']);

        return view('worksheets.edit', $this->formViewData($worksheet, 'edit', $request));
    }

    public function update(UpdateWorksheetRequest $request, Worksheet $worksheet): RedirectResponse
    {
        $this->ensureOwnedByCurrentUser($worksheet);

        DB::transaction(function () use ($request, $worksheet): void {
            $worksheet->update($request->safe()->only([
                'worksheet_number',
                'work_date',
                'note',
            ]));

            $this->syncWorksheetItems($worksheet, collect($request->validated('items')));
        });

        return redirect($this->resolveRedirectTarget($request))
            ->with('status', 'Munkalap frissítve.');
    }

    public function destroy(Request $request, Worksheet $worksheet): RedirectResponse
    {
        $this->ensureOwnedByCurrentUser($worksheet);
        $worksheet->delete();

        return redirect($this->resolveRedirectTarget($request))
            ->with('status', 'Munkalap törölve.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'worksheet_ids' => ['required', 'array', 'min:1'],
            'worksheet_ids.*' => ['integer', 'exists:worksheets,id'],
        ]);

        Worksheet::query()
            ->where('user_id', $request->user()->id)
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
        $billableItems = BillableItem::query()
            ->orderByRaw(BillableItemCatalog::orderBySql('name'))
            ->get();
        $billableItemOptions = $billableItems
            ->map(fn (BillableItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price,
                'allows_quantity' => BillableItemCatalog::allowsMultipleQuantity($item->name),
            ])
            ->values();
        $selectedItems = $worksheet->items
            ->map(fn (WorksheetItem $item) => [
                'worksheet_item_id' => $item->id,
                'billable_item_id' => $item->billable_item_id,
                'type' => $item->billable_item_id ? 'catalog' : 'custom',
                'quantity' => $item->quantity,
                'snapshot_name' => $item->item_name_at_time,
                'snapshot_price' => $item->price_at_time,
                'custom_name' => $item->billable_item_id ? null : $item->item_name_at_time,
                'custom_price' => $item->billable_item_id ? null : $item->price_at_time,
            ])
            ->values()
            ->all();

        return [
            'worksheet' => $worksheet,
            'billableItems' => $billableItems,
            'billableItemOptions' => $billableItemOptions,
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
        $existingCatalogItems = $worksheet->items()
            ->whereNotNull('billable_item_id')
            ->get()
            ->keyBy('billable_item_id');
        $existingCustomItems = $worksheet->items()
            ->whereNull('billable_item_id')
            ->get()
            ->keyBy('id');
        $keptItemIds = [];
        $handledBillableIds = [];

        foreach ($items as $itemData) {
            if (! is_array($itemData)) {
                continue;
            }

            if (($itemData['type'] ?? null) === 'custom') {
                $existingItem = $existingCustomItems->get((int) ($itemData['worksheet_item_id'] ?? 0));

                if ($existingItem instanceof WorksheetItem) {
                    $existingItem->update([
                        'item_name_at_time' => trim((string) $itemData['custom_name']),
                        'price_at_time' => (int) $itemData['custom_price'],
                        'quantity' => (int) $itemData['quantity'],
                    ]);

                    $keptItemIds[] = $existingItem->id;

                    continue;
                }

                $createdItem = $worksheet->items()->create([
                    'billable_item_id' => null,
                    'item_name_at_time' => trim((string) $itemData['custom_name']),
                    'price_at_time' => (int) $itemData['custom_price'],
                    'quantity' => (int) $itemData['quantity'],
                ]);

                $keptItemIds[] = $createdItem->id;

                continue;
            }

            $billableItemId = (int) $itemData['billable_item_id'];

            if (in_array($billableItemId, $handledBillableIds, true)) {
                continue;
            }

            $handledBillableIds[] = $billableItemId;
            $billableItem = BillableItem::query()->findOrFail($billableItemId);
            $quantity = BillableItemCatalog::allowsMultipleQuantity($billableItem->name)
                ? (int) $itemData['quantity']
                : 1;
            $existingItem = $existingCatalogItems->get($billableItemId);

            if ($existingItem instanceof WorksheetItem) {
                $existingItem->update([
                    'quantity' => $quantity,
                ]);

                $keptItemIds[] = $existingItem->id;

                continue;
            }

            $createdItem = $worksheet->items()->create([
                'billable_item_id' => $billableItem->id,
                'item_name_at_time' => $billableItem->name,
                'price_at_time' => $billableItem->price,
                'quantity' => $quantity,
            ]);

            $keptItemIds[] = $createdItem->id;
        }

        $deleteQuery = $worksheet->items();

        if ($keptItemIds !== []) {
            $deleteQuery->whereNotIn('id', array_unique($keptItemIds));
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
        $today = now()->toDateString();
        $rawFromInput = $request->string('from')->trim()->toString();
        $rawToInput = $request->string('to')->trim()->toString();
        $hasActiveFilter = $rawFromInput !== '' || $rawToInput !== '';
        $fromInput = $rawFromInput;
        $toInput = $rawToInput;

        if ($fromInput === '') {
            $fromInput = $today;
        }

        if ($toInput === '') {
            $toInput = $today;
        }

        $from = $this->parseDateInput($fromInput);
        $to = $this->parseDateInput($toInput);

        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to, $from];
            [$fromInput, $toInput] = [$toInput, $fromInput];
        }

        return [
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'from_display' => $fromInput,
            'to_display' => $toInput,
            'has_active_filter' => $hasActiveFilter,
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
            return now()->locale('hu')->translatedFormat('Y. F');
        }

        $start = $from ? Carbon::parse($from)->format('Y.m.d.') : Carbon::now()->format('Y.m.d.');
        $end = $to ? Carbon::parse($to)->format('Y.m.d.') : Carbon::now()->format('Y.m.d.');

        return "{$start}-{$end}";
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
            ->where('user_id', auth()->id())
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

    private function ensureOwnedByCurrentUser(Worksheet $worksheet): void
    {
        abort_unless((int) $worksheet->user_id === (int) auth()->id(), 404);
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
