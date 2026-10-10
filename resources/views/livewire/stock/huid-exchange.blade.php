<div>
    <x-ui.page-header title="HUID export and update" subtitle="Send pieces for hallmarking, then bring the HUIDs and making charges back through Excel."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'HUID export and update']]" />

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
        <x-ui.card title="1. Download" subtitle="{{ $waiting }} piece(s) have no HUID yet" icon="download">
            <div class="rj-segment mb-4">
                <button type="button" wire:click="$set('scope', 'waiting')" class="{{ $scope === 'waiting' ? 'is-active' : '' }}">Waiting for a HUID</button>
                <button type="button" wire:click="$set('scope', 'all')" class="{{ $scope === 'all' ? 'is-active' : '' }}">All pieces</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <div class="flex-1 text-[13px] text-ink_text-secondary"><span class="font-semibold text-ink_text-primary">HUID exporter.</span> The layout of the government HUID website's upload file.</div>
                    <x-ui.button icon="download" wire:click="exportHuidFormat">HUID file</x-ui.button>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex-1 text-[13px] text-ink_text-secondary"><span class="font-semibold text-ink_text-primary">Full data.</span> Every detail of each piece, to fill in the leftovers (HUIDs, making charges) and upload again.</div>
                    <x-ui.button variant="secondary" icon="download" wire:click="exportFull">Excel sheet</x-ui.button>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="2. Upload the filled sheet" subtitle="Rows are matched on the internal code" icon="upload">
            <label class="relative flex items-center gap-3.5 px-4 py-4 rounded-xl border-2 border-dashed border-line hover:border-gold hover:bg-gold-tint/40 cursor-pointer transition-colors">
                <input type="file" wire:model="sheet" accept=".csv,.xlsx" class="absolute inset-0 opacity-0 cursor-pointer" data-testid="huid-sheet">
                <span class="w-10 h-10 shrink-0 rounded-xl bg-white ring-1 ring-line text-gold-dark flex items-center justify-center"><x-ui.icon name="upload" :size="18" /></span>
                <span>
                    <span class="block text-[13px] font-semibold text-ink_text-primary">{{ $sheetName ?? 'Choose the Excel or CSV file' }}</span>
                    <span class="block text-[12px] text-ink_text-muted">The "Internal code" column is required. Empty cells are left alone.</span>
                </span>
            </label>
            @error('sheet')<p class="text-[12.5px] text-danger mt-2">{{ $message }}</p>@enderror

            @if ($applied !== null)
                <p class="mt-4 text-[13px] text-success font-semibold">{{ $applied }} piece(s) updated.</p>
            @endif
        </x-ui.card>
    </div>

    @if ($sheetName && ! $errors->has('sheet'))
        <x-ui.card :padding="false" class="mt-6" title="What will change" :subtitle="count($plan) . ' to update · ' . $unchanged . ' already match · ' . count($unmatched) . ' skipped'" icon="clipboard">
            <x-slot:actions>
                @if (count($plan))
                    <x-ui.button icon="check" wire:click="apply" target="apply">Apply {{ count($plan) }} update(s)</x-ui.button>
                @endif
            </x-slot:actions>
            @if (count($plan))
                <x-ui.table :headers="['Piece', 'Changes']">
                    @foreach (array_slice($plan, 0, 100) as $p)
                        <tr wire:key="pl-{{ $p['item_id'] }}">
                            <td class="rj-code">{{ $p['code'] }}</td>
                            <td class="text-[13px]">
                                @foreach ($p['changes'] as $field => [$old, $new])
                                    <span class="inline-block mr-3"><span class="text-ink_text-muted">{{ $labels[$field] ?? $field }}:</span> {{ $old === null || $old === '' ? 'empty' : $old }} <x-ui.icon name="arrow-right" :size="11" class="inline" /> <span class="font-semibold">{{ $new }}</span></span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @else
                <x-ui.empty-state icon="check-circle" title="Nothing to update" message="Every matched row already has these values." compact />
            @endif
            @if (count($unmatched))
                <div class="px-5 py-3 border-t border-line-light text-[12.5px] text-warning">Skipped: {{ implode(', ', array_slice($unmatched, 0, 20)) }}{{ count($unmatched) > 20 ? ' and ' . (count($unmatched) - 20) . ' more' : '' }}</div>
            @endif
        </x-ui.card>
    @endif
</div>
