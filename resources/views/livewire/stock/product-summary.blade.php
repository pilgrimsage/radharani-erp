<div>
    <x-ui.page-header title="Product view" subtitle="Pieces and weight by metal, then by category, carat or price range in the order you choose."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Product view']]" />

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        @foreach ($cards as $c)
            <button type="button" wire:click="$set('metal', '{{ $active === $c['metal'] ? '' : $c['metal'] }}')"
                @class(['text-left rounded-card p-5 bg-white border shadow-card transition-colors', 'border-gold ring-2 ring-gold/30' => $active === $c['metal'], 'border-line-light hover:border-gold-soft' => $active !== $c['metal']])>
                <div class="flex items-center gap-2 text-[12.5px] font-semibold text-ink_text-secondary"><x-movement.metal-dot :metal="$c['metal']" /> {{ ucfirst($c['metal']) }}</div>
                <div class="font-display text-[34px] leading-tight font-semibold tabular mt-1">{{ number_format($c['pieces']) }}<span class="text-[15px] text-ink_text-muted ml-1">pieces</span></div>
                <div class="text-[13px] text-ink_text-secondary tabular">{{ number_format($c['weight'], 3) }} g</div>
            </button>
        @endforeach
        @if ($cards->isEmpty())
            <div class="col-span-full"><x-ui.empty-state icon="gem" title="No stock yet" message="Add pieces to see them grouped here." /></div>
        @endif
    </div>

    @if ($cards->isNotEmpty())
        <x-ui.card :padding="false" :title="($active ? ucfirst($active) : 'All metals')" :subtitle="number_format($total['pieces']) . ' pieces · ' . number_format($total['weight'], 3) . ' g'" icon="layers">
            <x-slot:actions>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[12.5px] text-ink_text-muted">Break down by</span>
                    @foreach (['level1' => false, 'level2' => true, 'level3' => true] as $prop => $optional)
                        <select wire:model.live="{{ $prop }}" class="rj-select w-auto min-w-[130px]" aria-label="Level {{ $loop->iteration }}">
                            @if ($optional)<option value="">{{ $loop->iteration === 2 ? 'Then by...' : 'Then by...' }}</option>@endif
                            @foreach ($dimensions as $v => $label)<option value="{{ $v }}">{{ $label }}</option>@endforeach
                        </select>
                    @endforeach
                </div>
            </x-slot:actions>
            <x-ui.table :headers="['Group', 'Pieces', 'Weight']">
                @foreach ($rows as $r)
                    <tr wire:key="row-{{ $loop->index }}">
                        <td style="padding-left: {{ 1 + $r['depth'] * 1.5 }}rem" @class(['font-semibold text-ink_text-primary' => $r['depth'] === 0, 'text-ink_text-secondary' => $r['depth'] > 0])>
                            {{ $r['label'] }}
                        </td>
                        <td class="tabular">{{ number_format($r['pieces']) }}</td>
                        <td class="tabular">{{ number_format($r['weight'], 3) }} g</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
