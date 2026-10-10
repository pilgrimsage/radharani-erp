<div>
    <x-ui.page-header :title="$location->name" subtitle="What is here right now."
        :crumbs="[['label' => 'Reports'], ['label' => 'Locations', 'href' => route('reports.location')], ['label' => $location->name]]">
        <x-slot:meta>
            <x-ui.badge tone="gold">{{ \App\Models\Location::TYPES[$location->type] }}</x-ui.badge>
            @unless ($location->is_active)<x-ui.badge>Off</x-ui.badge>@endunless
        </x-slot:meta>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="archive" label="Boxes recorded here" :value="$boxes->count()" />
        <x-ui.stat-card icon="package" label="Packets recorded here" :value="$packets->count()" />
        <x-ui.stat-card icon="gem" label="Pieces" :value="number_format($itemCount)" />
        <x-ui.stat-card icon="scale" label="Weight" :value="number_format($weight, 3).' g'" />
    </div>

    @if ($boxes->isNotEmpty() || $packets->isNotEmpty())
        <x-ui.card title="Boxes and packets" icon="archive" class="mb-6">
            <div class="flex flex-wrap gap-2">
                @foreach ($boxes as $b)
                    <a href="{{ route('stock.boxes.show', $b) }}" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md bg-surface-sunken ring-1 ring-inset ring-line-light rj-code text-[12.5px] hover:ring-gold-soft"><x-ui.icon name="archive" :size="13" /> {{ $b->code }}</a>
                @endforeach
                @foreach ($packets as $p)
                    <a href="{{ route('stock.packets.show', $p) }}" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md bg-surface-sunken ring-1 ring-inset ring-line-light rj-code text-[12.5px] hover:ring-gold-soft"><x-ui.icon name="package" :size="13" /> {{ $p->code }}{{ $p->box ? ' · ' . $p->box->code : '' }}</a>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    <x-ui.card :padding="false" title="Pieces here" :subtitle="$itemCount > $items->count() ? 'Showing the first ' . $items->count() . ' of ' . $itemCount : null" icon="gem" class="mb-6">
        <x-ui.table :headers="['Piece', 'Category', 'Weight', 'Packet', 'Box']">
            @forelse ($items as $i)
                <tr wire:key="it-{{ $i->id }}">
                    <td><a href="{{ route('stock.items.show', $i) }}" class="rj-code hover:text-gold-dark">{{ $i->label }}</a></td>
                    <td>{{ $i->category }}</td>
                    <td class="tabular">{{ number_format($i->weight, 3) }} g</td>
                    <td class="rj-code text-[12.5px]">{{ $i->packet?->code ?? '-' }}</td>
                    <td class="rj-code text-[12.5px]">{{ $i->packet?->box?->code ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><x-ui.empty-state icon="archive" title="Nothing here" message="No pieces are recorded at this location." compact /></td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>

    <x-ui.card :padding="false" title="Recent moves to this location" icon="history">
        <x-ui.table :headers="['When', 'What', 'By']">
            @forelse ($recent as $m)
                <tr>
                    <td class="whitespace-nowrap tabular text-ink_text-secondary">{{ $m->created_at->format('j M, g:i a') }}</td>
                    <td>{{ ucfirst($m->trackable_type) }} #{{ $m->trackable_id }} <span class="text-ink_text-muted text-[12.5px]">{{ $m->purpose_label }}</span></td>
                    <td>{{ $m->user?->name ?? '-' }}@if ($m->doneBy) <span class="text-ink_text-muted text-[12px]">(done by {{ $m->doneBy->name }})</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="3"><x-ui.empty-state icon="clock" title="No moves recorded" compact /></td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
</div>
