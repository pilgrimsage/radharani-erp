<div>
    <x-ui.page-header title="Location Report" subtitle="Where the stock is right now: pieces and weight for every location."
        :crumbs="[['label' => 'Reports'], ['label' => 'Locations']]" />

    <div class="grid grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
        <x-ui.stat-card icon="map-pin" label="In-store locations" :value="number_format($stats['locations'])" />
        <x-ui.stat-card icon="gem" label="Pieces" :value="number_format($stats['items'])" />
        <x-ui.stat-card icon="scale" label="Weight" :value="number_format($stats['weight'], 3).' g'" />
    </div>

    <x-ui.card :padding="false" class="overflow-hidden">
        <x-ui.table :headers="['Location', 'Pieces', 'Weight', '']">
            @forelse ($rows as $row)
                <tr class="h-[56px] border-b border-line-light">
                    <td class="px-4">
                        @if ($row['location'])
                            <a href="{{ route('reports.location.show', $row['location']) }}" class="font-semibold text-ink_text-primary hover:text-gold-dark">{{ $row['label'] }}</a>
                            @unless ($row['location']->is_active)<x-ui.badge size="sm">Off</x-ui.badge>@endunless
                        @else
                            <x-ui.badge tone="neutral">{{ $row['label'] }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 tabular">{{ $row['qty'] }}</td>
                    <td class="px-4 tabular">{{ number_format($row['weight'], 3) }} g</td>
                    <td class="px-4 text-right">
                        @if ($row['location'])
                            <x-ui.button variant="secondary" size="sm" iconRight="arrow-right" :href="route('reports.location.show', $row['location'])">Open</x-ui.button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><x-ui.empty-state icon="archive" title="No locations yet" message="Add locations under Administration." compact /></td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
</div>
