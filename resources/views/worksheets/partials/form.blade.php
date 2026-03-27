@php
    $isEdit = $mode === 'edit';
@endphp

<div
    class="mx-auto max-w-[760px]"
    x-data="worksheetForm({
        initialSelected: @js($selectedItems),
        billableItems: @js($billableItems->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'price' => $item->price])->values()),
    })"
>
<form
    method="POST"
    action="{{ $isEdit ? route('worksheets.update', $worksheet) : route('worksheets.store') }}"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">

    <section class="panel-shell overflow-hidden">
        <div class="border-b border-white/10 px-5 py-5 sm:px-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-xl font-semibold italic tracking-tight text-white sm:text-2xl">Munkalap rögzítése</h1>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ $redirectTo }}" class="btn-text">Vissza</a>

                        @if ($isEdit)
                            <button type="button" class="btn-danger-outline" x-on:click="deleteModalOpen = true">Törlés</button>
                        @endif
                    </div>
                </div>
            </div>

            <p class="mt-3 text-sm text-slate-400">{{ $isEdit ? 'Korábbi munkalap frissítése' : 'Új munkalap létrehozása' }}</p>
        </div>

        <div class="space-y-8 px-5 py-6 sm:px-6 sm:py-7">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="work_date" class="field-label">Dátum</label>
                    <div class="date-shell">
                        <input id="work_date" name="work_date" type="date" max="{{ $todayDate }}" class="date-input date-input-native w-full border-0 bg-transparent px-0 py-0 focus:ring-0" value="{{ $workDateInput }}" required>
                    </div>
                </div>

                <div>
                    <label for="worksheet_number" class="field-label">Munkalap száma</label>
                    <input id="worksheet_number" name="worksheet_number" type="text" class="input-dark" value="{{ old('worksheet_number', $worksheet->worksheet_number) }}" placeholder="Pl. 2026/001" required>
                </div>
            </div>

            <div>
                <div class="mb-4 flex flex-col items-start gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                    <label class="field-label !mb-0">Elvégzett tételek</label>
                    <div class="text-xs text-slate-400 sm:text-sm">Összesen: <span class="font-semibold text-white" x-text="formatCurrency(totalAmount())"></span></div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($billableItems as $item)
                        <div class="item-card" :class="isSelected({{ $item->id }}) ? 'item-card-active' : ''">
                            <button type="button" class="block w-full text-left" x-on:click="toggleItem({ id: {{ $item->id }}, name: @js($item->name), price: {{ $item->price }} })">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="text-left">
                                        <div class="text-base font-semibold text-white">{{ $item->name }}</div>
                                        <div class="mt-1 text-xs font-medium text-slate-500">{{ number_format($item->price, 0, ',', ' ') }} Ft</div>
                                    </div>

                                    <span class="selection-dot" :class="isSelected({{ $item->id }}) ? 'selection-dot-active' : ''"></span>
                                </div>
                            </button>
                        </div>
                    @endforeach
                </div>

                <template x-for="(item, index) in selectedItems" :key="item.billable_item_id">
                    <div>
                        <input type="hidden" :name="`items[${index}][worksheet_item_id]`" :value="item.worksheet_item_id ?? ''">
                        <input type="hidden" :name="`items[${index}][billable_item_id]`" :value="item.billable_item_id">
                        <input type="hidden" :name="`items[${index}][quantity]`" value="1">
                    </div>
                </template>
            </div>

            <div>
                <label for="note" class="field-label">Megjegyzés</label>
                <textarea id="note" name="note" rows="3" class="textarea-dark" placeholder="Opcionális megjegyzések...">{{ old('note', $worksheet->note) }}</textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-white/10 px-5 py-5 sm:px-6">
            <a href="{{ $redirectTo }}" class="btn-text">Mégse</a>
            <button type="submit" class="btn-primary">Mentés</button>
        </div>
    </section>

</form>

@if ($isEdit)
    <form id="delete-worksheet-form" method="POST" action="{{ route('worksheets.destroy', $worksheet) }}" class="hidden">
        @csrf
        @method('DELETE')
        <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
    </form>

    <div
        x-cloak
        x-show="deleteModalOpen"
        class="fixed inset-0 z-50 flex items-end justify-center bg-[#040814]/80 p-4 backdrop-blur-sm sm:items-center"
        x-on:keydown.escape.window="deleteModalOpen = false"
    >
        <div class="absolute inset-0" x-on:click="deleteModalOpen = false"></div>

        <div class="relative z-10 w-full max-w-md rounded-2xl border border-white/10 bg-[#151d2d] p-6 shadow-[0_24px_60px_rgba(0,0,0,0.45)]">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-red-400/80">Megerősítés</p>
            <h2 class="mt-3 text-xl font-semibold text-white">Munkalap törlése</h2>
            <p class="mt-3 text-sm leading-6 text-slate-400">A munkalap és a hozzá tartozó tételek végleg törlődnek.</p>

            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                <button type="button" class="btn-text" x-on:click="deleteModalOpen = false">Mégse</button>
                <button type="submit" form="delete-worksheet-form" class="btn-danger-outline">Törlés</button>
            </div>
        </div>
    </div>
@endif
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('worksheetForm', ({ initialSelected, billableItems }) => ({
            billableItems,
            selectedItems: initialSelected,
            deleteModalOpen: false,
            toggleItem(item) {
                const existingIndex = this.selectedItems.findIndex((selected) => Number(selected.billable_item_id) === Number(item.id));

                if (existingIndex >= 0) {
                    this.selectedItems.splice(existingIndex, 1);
                    return;
                }

                this.selectedItems.push({
                    worksheet_item_id: null,
                    billable_item_id: item.id,
                    quantity: 1,
                    snapshot_name: item.name,
                    snapshot_price: item.price,
                });
            },
            isSelected(itemId) {
                return this.selectedItems.some((item) => Number(item.billable_item_id) === Number(itemId));
            },
            totalAmount() {
                return this.selectedItems.reduce((sum, item) => {
                    const source = this.billableItems.find((billableItem) => Number(billableItem.id) === Number(item.billable_item_id));
                    return sum + (source?.price ?? 0);
                }, 0);
            },
            formatCurrency(value) {
                return `${new Intl.NumberFormat('hu-HU').format(value ?? 0)} Ft`;
            },
        }));
    });
</script>
