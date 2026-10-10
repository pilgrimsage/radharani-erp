@php $fmt = fn ($v) => $v === null ? '' : (is_float($v) ? number_format($v, 3) : $v); @endphp
<div>
    <x-ui.page-header :title="$party->name" :subtitle="($party->type === 'karigar' ? 'Karigar' : 'Hallmarking centre') . ' ledger. Weights and counts only.'"
        :crumbs="[['label' => 'Reports'], ['label' => 'Ledgers', 'href' => route('ledgers.index', ['kind' => $party->type === 'karigar' ? 'karigar' : 'hallmarker'])], ['label' => $party->name]]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" :href="route('ledgers.party.download', [$party, 'xlsx'])">Excel</x-ui.button>
            <x-ui.button icon="download" :href="route('ledgers.party.download', [$party, 'pdf'])">PDF</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="overflow-x-auto">
            <x-ui.table :headers="$ledger['columns']">
                @forelse ($ledger['rows'] as $r)
                    <tr>
                        <td class="whitespace-nowrap text-ink_text-secondary">{{ $r['at']->format('j M Y, g:i a') }}</td>
                        <td>{{ $r['label'] }}</td>
                        @foreach ($r['cells'] as $c)<td class="tabular">{{ $fmt($c) }}</td>@endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($ledger['columns']) }}"><x-ui.empty-state icon="book" title="Nothing recorded yet" compact /></td></tr>
                @endforelse
                @if ($ledger['rows']->isNotEmpty())
                    <tr class="font-semibold bg-surface-sunken"><td colspan="2">Total</td>@foreach ($ledger['totals'] as $t)<td class="tabular">{{ $fmt($t) }}</td>@endforeach</tr>
                @endif
            </x-ui.table>
        </div>
    </x-ui.card>
</div>
