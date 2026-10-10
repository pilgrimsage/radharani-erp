<div>
    <x-ui.page-header title="Ledgers" subtitle="Weights and counts per karigar and per hallmarking centre. No money."
        :crumbs="[['label' => 'Reports'], ['label' => 'Ledgers']]">
        <x-slot:actions>
            <div class="rj-segment">
                <button type="button" wire:click="$set('kind', 'karigar')" class="{{ $kind === 'karigar' ? 'is-active' : '' }}">Karigars</button>
                <button type="button" wire:click="$set('kind', 'hallmarker')" class="{{ $kind === 'hallmarker' ? 'is-active' : '' }}">Hallmarkers</button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <x-ui.table :headers="array_merge([$kind === 'karigar' ? 'Karigar' : 'Centre'], $columns, [''])">
            @forelse ($parties as $row)
                <tr wire:key="lp-{{ $row['party']->id }}">
                    <td class="font-semibold text-ink_text-primary"><a href="{{ route('ledgers.party', $row['party']) }}" class="hover:text-gold-dark">{{ $row['party']->name }}</a></td>
                    @foreach ($row['totals'] as $t)
                        <td class="tabular">{{ is_float($t) ? number_format($t, 3) : $t }}</td>
                    @endforeach
                    <td class="text-right"><x-ui.button variant="secondary" size="sm" iconRight="arrow-right" :href="route('ledgers.party', $row['party'])">Open</x-ui.button></td>
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 2 }}"><x-ui.empty-state icon="book" title="Nobody yet" message="Add them under Administration, Karigars & Centres." compact /></td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
</div>
