@php
    $isEdit = $mode === 'edit';
@endphp

<div
    class="mx-auto max-w-[760px]"
    x-data="worksheetForm({
        initialSelected: @js($selectedItems),
        billableItems: @js($billableItemOptions),
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
                    <h1 class="text-xl font-semibold tracking-tight text-white sm:text-2xl">Munkalap rögzítése</h1>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ $redirectTo }}" class="btn-text">Vissza</a>

                        @if ($isEdit)
                            <button type="button" class="btn-danger-outline" x-on:click="deleteModalOpen = true">Törlés</button>
                        @endif
                    </div>
                </div>
            </div>
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
                <p id="worksheet-item-list-label" class="field-label">Elvégzett tételek</p>

                <div id="worksheet-item-list" class="grid gap-3 sm:grid-cols-2" role="group" aria-labelledby="worksheet-item-list-label">
                    @foreach ($billableItems as $item)
                        @php
                            $billableOption = $billableItemOptions->firstWhere('id', $item->id);
                        @endphp

                        <div class="item-card" :class="isSelected({{ $item->id }}) ? 'item-card-active' : ''">
                            <button type="button" class="block w-full text-left" x-on:click="toggleItem(@js($billableOption))">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="text-left">
                                        <div class="text-base font-semibold text-white">{{ $item->name }}</div>
                                        <div class="mt-1 text-xs font-medium text-slate-500">{{ number_format($item->price, 0, ',', ' ') }} Ft</div>
                                    </div>

                                    <span class="selection-dot" :class="isSelected({{ $item->id }}) ? 'selection-dot-active' : ''"></span>
                                </div>
                            </button>

                            <div x-cloak x-show="isSelected({{ $item->id }}) && allowsQuantity({{ $item->id }})" class="mt-3 flex items-center justify-between gap-3 border-t border-white/10 pt-3" x-on:click.stop>
                                <label for="quantity_{{ $item->id }}" class="mb-0 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Darab</label>
                                <input
                                    id="quantity_{{ $item->id }}"
                                    type="number"
                                    min="1"
                                    max="999"
                                    step="1"
                                    inputmode="numeric"
                                    class="quantity-input-dark"
                                    :value="selectedCatalogQuantity({{ $item->id }})"
                                    x-on:input="updateCatalogQuantity({{ $item->id }}, $event.target.value)"
                                >
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="field-label mb-0">Egyedi tételek</p>
                        <button type="button" class="btn-accent btn-compact" x-on:click="addCustomItem()">+ Egyedi tétel</button>
                    </div>

                    <div x-cloak x-show="customItems().length > 0" class="space-y-3">
                        <template x-for="item in customItems()" :key="item.key">
                            <div class="custom-item-card">
                                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(8rem,10rem)_minmax(5.5rem,6.5rem)]">
                                    <div>
                                        <label class="field-label" :for="`${item.key}_name`">Név</label>
                                        <input :id="`${item.key}_name`" type="text" class="input-dark" x-model="item.custom_name" placeholder="Pl. extra kiszállás">
                                    </div>

                                    <div>
                                        <label class="field-label" :for="`${item.key}_price`">Összeg</label>
                                        <input :id="`${item.key}_price`" type="number" min="1" step="1" inputmode="numeric" class="input-dark" x-model="item.custom_price" placeholder="Ft">
                                    </div>

                                    <div>
                                        <label class="field-label" :for="`${item.key}_quantity`">Darab</label>
                                        <input :id="`${item.key}_quantity`" type="number" min="1" max="999" step="1" inputmode="numeric" class="input-dark" x-model="item.quantity">
                                    </div>
                                </div>

                                <div class="mt-3 flex justify-end">
                                    <button type="button" class="btn-danger-outline btn-compact" x-on:click="removeCustomItem(item.key)">Törlés</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <template x-for="(item, index) in selectedItems" :key="`fields-${item.key}`">
                    <div>
                        <input type="hidden" :name="`items[${index}][type]`" :value="item.type">
                        <input type="hidden" :name="`items[${index}][worksheet_item_id]`" :value="item.worksheet_item_id ?? ''">
                        <input type="hidden" :name="`items[${index}][billable_item_id]`" :value="item.billable_item_id ?? ''">
                        <input type="hidden" :name="`items[${index}][custom_name]`" :value="item.custom_name ?? ''">
                        <input type="hidden" :name="`items[${index}][custom_price]`" :value="item.custom_price ?? ''">
                        <input type="hidden" :name="`items[${index}][quantity]`" :value="quantityForSubmission(item)">
                    </div>
                </template>
            </div>

            <div class="text-left">
                <div class="text-base text-slate-400 m:text-m">Összesen: <span class="font-semibold text-white" x-text="formatCurrency(totalAmount())"></span></div>
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
            selectedItems: [],
            deleteModalOpen: false,
            nextCustomKey: 1,
            init() {
                const sourceItems = Array.isArray(initialSelected) ? initialSelected : Object.values(initialSelected ?? {});

                this.selectedItems = sourceItems.map((item, index) => this.normalizeInitialItem(item, index));
                this.nextCustomKey = this.selectedItems.length + 1;
            },
            normalizeInitialItem(item, index) {
                const billableItemId = this.toNullableNumber(item.billable_item_id);
                const type = item.type === 'custom' || billableItemId === null ? 'custom' : 'catalog';
                const source = billableItemId ? this.findBillableItem(billableItemId) : null;

                return {
                    key: item.key ?? (type === 'custom' ? `custom-existing-${item.worksheet_item_id ?? index}` : `catalog-${billableItemId}`),
                    type,
                    worksheet_item_id: item.worksheet_item_id ?? null,
                    billable_item_id: type === 'catalog' ? billableItemId : null,
                    quantity: this.clampQuantity(item.quantity ?? 1),
                    snapshot_name: item.snapshot_name ?? source?.name ?? '',
                    snapshot_price: this.normalizeMoney(item.snapshot_price ?? source?.price ?? 0),
                    custom_name: item.custom_name ?? (type === 'custom' ? item.snapshot_name ?? '' : ''),
                    custom_price: item.custom_price ?? (type === 'custom' ? item.snapshot_price ?? '' : ''),
                };
            },
            toggleItem(item) {
                const existingIndex = this.selectedItems.findIndex((selected) => selected.type === 'catalog' && Number(selected.billable_item_id) === Number(item.id));

                if (existingIndex >= 0) {
                    this.selectedItems.splice(existingIndex, 1);
                    return;
                }

                this.selectedItems.push({
                    key: `catalog-${item.id}`,
                    type: 'catalog',
                    worksheet_item_id: null,
                    billable_item_id: item.id,
                    quantity: 1,
                    snapshot_name: item.name,
                    snapshot_price: item.price,
                    custom_name: '',
                    custom_price: '',
                });
            },
            isSelected(itemId) {
                return this.selectedItems.some((item) => item.type === 'catalog' && Number(item.billable_item_id) === Number(itemId));
            },
            findBillableItem(itemId) {
                return this.billableItems.find((billableItem) => Number(billableItem.id) === Number(itemId));
            },
            allowsQuantity(itemId) {
                return Boolean(this.findBillableItem(itemId)?.allows_quantity);
            },
            selectedCatalogItem(itemId) {
                return this.selectedItems.find((item) => item.type === 'catalog' && Number(item.billable_item_id) === Number(itemId));
            },
            selectedCatalogQuantity(itemId) {
                return this.selectedCatalogItem(itemId)?.quantity ?? 1;
            },
            updateCatalogQuantity(itemId, value) {
                const item = this.selectedCatalogItem(itemId);

                if (!item) {
                    return;
                }

                item.quantity = this.clampQuantity(value);
            },
            addCustomItem() {
                this.selectedItems.push({
                    key: `custom-${this.nextCustomKey++}`,
                    type: 'custom',
                    worksheet_item_id: null,
                    billable_item_id: null,
                    quantity: 1,
                    snapshot_name: '',
                    snapshot_price: 0,
                    custom_name: '',
                    custom_price: '',
                });
            },
            removeCustomItem(key) {
                this.selectedItems = this.selectedItems.filter((item) => item.key !== key);
            },
            customItems() {
                return this.selectedItems.filter((item) => item.type === 'custom');
            },
            totalAmount() {
                return this.selectedItems.reduce((sum, item) => {
                    const quantity = this.quantityForSubmission(item);

                    if (item.type === 'custom') {
                        return sum + (this.normalizeMoney(item.custom_price) * quantity);
                    }

                    const source = this.findBillableItem(item.billable_item_id);
                    const price = this.normalizeMoney(item.snapshot_price || source?.price || 0);

                    return sum + (price * quantity);
                }, 0);
            },
            quantityForSubmission(item) {
                if (item.type === 'catalog' && !this.allowsQuantity(item.billable_item_id)) {
                    return 1;
                }

                return this.clampQuantity(item.quantity);
            },
            clampQuantity(value) {
                const number = Number.parseInt(value, 10);

                if (!Number.isFinite(number)) {
                    return 1;
                }

                return Math.min(999, Math.max(1, number));
            },
            normalizeMoney(value) {
                const number = Number.parseInt(String(value ?? '').replace(/\s+/g, ''), 10);

                if (!Number.isFinite(number) || number < 1) {
                    return 0;
                }

                return number;
            },
            toNullableNumber(value) {
                if (value === null || value === undefined || value === '') {
                    return null;
                }

                const number = Number(value);

                return Number.isFinite(number) ? number : null;
            },
            formatCurrency(value) {
                return `${new Intl.NumberFormat('hu-HU').format(value ?? 0)} Ft`;
            },
        }));
    });
</script>
