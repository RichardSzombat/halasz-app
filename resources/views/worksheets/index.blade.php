@extends('layouts.app', [
    'title' => 'Munkalapok - Halasz Worksheet System',
])

@section('content')
    <div x-data="worksheetSelection()" class="space-y-6 sm:space-y-8">
        <div class="grid gap-3 sm:gap-4 lg:grid-cols-2">
            @include('worksheets.partials.summary-card', [
                'label' => 'Havi bevétel',
                'value' => $rangeTotal,
                'caption' => $activeRangeLabel,
                'icon' => 'chart',
            ])

            @include('worksheets.partials.summary-card', [
                'label' => 'Napi bevétel (ma)',
                'value' => $dailyTotal,
                'caption' => 'A mai naphoz tartozó összes rögzített bevétel.',
                'icon' => 'money',
            ])
        </div>

        <section class="panel-shell overflow-hidden">
            <div class="border-b border-white/10 px-4 py-4 sm:px-5">
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="section-title">Munkalapok</h1>
                            <a href="{{ route('worksheets.create', ['redirect_to' => url()->current()]) }}" class="btn-primary btn-compact">+ Új munkalap</a>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
                            <form method="POST" action="{{ route('worksheets.reset') }}">
                                @csrf
                                <button type="submit" class="btn-text">Reset</button>
                            </form>

                            <form method="POST" action="{{ route('worksheets.bulkDelete') }}" x-ref="bulkDeleteForm">
                                @csrf
                                <input type="hidden" name="from" value="{{ $filters['from_display'] }}">
                                <input type="hidden" name="to" value="{{ $filters['to_display'] }}">
                                <input type="hidden" name="sort" value="{{ $sortKey }}">
                                <input type="hidden" name="redirect_to" value="{{ url()->current() }}">

                                <template x-for="id in selectedIds" :key="id">
                                    <input type="hidden" name="worksheet_ids[]" :value="id">
                                </template>

                                <button type="button" class="btn-danger-outline btn-compact" x-text="buttonLabel()" x-on:click="handleAction()"></button>
                            </form>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('worksheets.index') }}" class="flex min-w-0 flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                        <div class="grid min-w-0 grid-cols-2 gap-2 sm:min-w-[320px] sm:gap-3 xl:flex-1">
                            <div class="date-filter-group min-w-0">
                                <label for="from" class="filter-label">Ettől</label>
                                <input id="from" name="from" type="date" max="{{ $todayDate }}" class="date-input date-input-native" value="{{ $filterInputs['from'] }}">
                            </div>

                            <div class="date-filter-group min-w-0">
                                <label for="to" class="filter-label">Eddig</label>
                                <input id="to" name="to" type="date" max="{{ $todayDate }}" class="date-input date-input-native" value="{{ $filterInputs['to'] }}">
                            </div>
                        </div>

                        <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end sm:gap-3 xl:flex-nowrap xl:justify-end">
                            <select id="sort" name="sort" class="select-dark sm:w-[180px] xl:w-[190px]">
                                <option value="">Dátum szerint</option>
                                <option value="date_asc" @selected($sortKey === 'date_asc')>Dátum szerint, régi elöl</option>
                                <option value="name_asc" @selected($sortKey === 'name_asc')>Munkalap szerint, A-Z</option>
                                <option value="name_desc" @selected($sortKey === 'name_desc')>Munkalap szerint, Z-A</option>
                            </select>

                            <button type="submit" formaction="{{ route('worksheets.export') }}" class="btn-accent btn-compact sm:self-end">Export XLS</button>
                            <button type="submit" class="btn-primary btn-compact sm:self-end">Szűrés</button>
                        </div>
                    </form>
                </div>
            </div>

            @if ($worksheets->isEmpty())
                <div class="px-6 py-16 text-center">
                    <h2 class="text-lg font-semibold text-white sm:text-xl">Nincs megjeleníthető munkalap</h2>
                    <p class="mt-2 text-sm text-slate-400">Hozz létre új rekordot, vagy állítsd vissza a szűrőket.</p>
                </div>
            @else
                <div class="overflow-hidden">
                    <table class="worksheet-table">
                        <colgroup>
                            <col class="w-[58px] sm:w-[108px]">
                            <col>
                            <col class="w-[76px] sm:w-[108px]">
                        </colgroup>

                        <thead>
                            <tr>
                                <th class="worksheet-head">Dátum</th>
                                <th class="worksheet-head">Munkalap & tételek</th>
                                <th class="worksheet-head text-right">Összeg</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($worksheets as $worksheet)
                                <tr
                                    class="worksheet-row"
                                    x-bind:class="selectionMode ? 'cursor-default' : 'cursor-pointer'"
                                    x-on:click="openWorksheet('{{ route('worksheets.edit', ['worksheet' => $worksheet, 'redirect_to' => url()->current()]) }}')"
                                >
                                    <td class="worksheet-cell align-top">
                                        <div class="flex items-start gap-2">
                                            <div x-show="selectionMode" class="shrink-0 pt-0.5">
                                                <input type="checkbox" class="checkbox-dark" :value="{{ $worksheet->id }}" x-model="selectedIds" x-on:click.stop>
                                            </div>

                                            <div class="date-display">
                                                <span class="block sm:hidden">{{ $worksheet->work_date->format('Y') }}</span>
                                                <span class="block sm:hidden">{{ $worksheet->work_date->format('m.d') }}</span>
                                                <span class="hidden sm:inline">{{ $worksheet->work_date->format('Y.m.d') }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="worksheet-cell align-top">
                                        <div class="min-w-0 space-y-1">
                                            <div class="truncate text-[11px] font-semibold leading-4 text-white sm:text-[13px] sm:whitespace-normal sm:break-words">{{ $worksheet->worksheet_number }}</div>

                                            <div class="flex flex-wrap items-center gap-1 text-[10px] leading-4 text-slate-400">
                                                @foreach ($worksheet->items as $item)
                                                    <span class="tag-pill" style="{{ $tagPalette[$item->item_name_at_time] ?? '' }}">{{ $item->item_name_at_time }}</span>
                                                @endforeach

                                                @if ($worksheet->note)
                                                    <span class="break-words text-[10px] leading-4 text-slate-400/90">{{ $worksheet->note }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td class="worksheet-cell align-top text-right">
                                        <div class="inline-flex items-baseline gap-1 whitespace-nowrap text-[13px] font-bold leading-4 text-white sm:text-[15px]">
                                            <span>{{ number_format($worksheet->calculated_total, 0, ',', ' ') }}</span>
                                            <span class="text-[10px] font-medium uppercase tracking-[0.08em] text-slate-500">Ft</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div
            x-cloak
            x-show="modalOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-[#040814]/80 p-4 backdrop-blur-sm sm:items-center"
            x-on:keydown.escape.window="closeModal()"
        >
            <div class="absolute inset-0" x-on:click="closeModal()"></div>

            <div class="relative z-10 w-full max-w-md rounded-2xl border border-white/10 bg-[#151d2d] p-6 shadow-[0_24px_60px_rgba(0,0,0,0.45)]">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-red-400/80">Megerősítés</p>
                <h2 class="mt-3 text-xl font-semibold text-white">Kijelölt munkalapok törlése</h2>
                <p class="mt-3 text-sm leading-6 text-slate-400">A kijelölt munkalapok és a hozzájuk tartozó tételek végleg törlődnek.</p>

                <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-text" x-on:click="closeModal()">Mégse</button>
                    <button type="button" class="btn-danger-outline" x-on:click="$refs.bulkDeleteForm.requestSubmit(); closeModal()">Törlés</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('worksheetSelection', () => ({
                selectionMode: false,
                selectedIds: [],
                modalOpen: false,
                buttonLabel() {
                    if (!this.selectionMode) {
                        return 'Kijelölés';
                    }

                    if (this.selectedIds.length === 0) {
                        return 'Mégse';
                    }

                    return `Törlés (${this.selectedIds.length})`;
                },
                handleAction() {
                    if (!this.selectionMode) {
                        this.selectionMode = true;
                        return;
                    }

                    if (this.selectedIds.length === 0) {
                        this.selectionMode = false;
                        return;
                    }

                    this.modalOpen = true;
                },
                closeModal() {
                    this.modalOpen = false;
                },
                openWorksheet(url) {
                    if (this.selectionMode) {
                        return;
                    }

                    window.location.href = url;
                },
            }));
        });
    </script>
@endsection
