<div>
    <x-ui.page-header title="Locations" subtitle="Where stock can be in the shop. Several counters and displays are fine."
        :crumbs="[['label' => 'Administration'], ['label' => 'Locations']]">
        <x-slot:actions>
            <x-ui.button icon="plus" wire:click="create">New location</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <x-ui.table :headers="['Location', 'Type', 'Order', 'Moves', 'Status', '']">
            @foreach ($locations as $l)
                <tr wire:key="loc-{{ $l->id }}" class="{{ $l->is_active ? '' : 'opacity-60' }}">
                    <td class="font-semibold text-ink_text-primary">
                        @if (\Illuminate\Support\Facades\Route::has('reports.location.show'))
                            <a href="{{ route('reports.location.show', $l) }}" class="hover:text-gold-dark">{{ $l->name }}</a>
                        @else {{ $l->name }} @endif
                    </td>
                    <td><x-ui.badge size="sm" :tone="$l->type === 'vault' ? 'dark' : 'gold'">{{ $types[$l->type] }}</x-ui.badge></td>
                    <td class="tabular">{{ $l->sort_order }}</td>
                    <td class="tabular text-ink_text-secondary">{{ $l->movements_count }}</td>
                    <td><x-ui.badge size="sm" :tone="$l->is_active ? 'success' : 'neutral'">{{ $l->is_active ? 'On' : 'Off' }}</x-ui.badge></td>
                    <td>
                        <div class="flex justify-end gap-1">
                            <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $l->id }})" aria-label="Edit {{ $l->name }}" />
                            @if ($l->type !== 'vault')
                                <x-ui.button variant="secondary" size="sm" wire:click="toggle({{ $l->id }})">{{ $l->is_active ? 'Switch off' : 'Switch on' }}</x-ui.button>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
    <p class="rj-help mt-3">Locations are never deleted, so past movements keep their place. Switch one off to stop using it.</p>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit location' : 'New location'" icon="map-pin" max-width="md" submit="save">
        <div class="space-y-4">
            <x-ui.field label="Name" for="loc-name" error="name" hint="For example Counter 2 or Window display.">
                <input id="loc-name" type="text" wire:model="name" maxlength="60" class="rj-input @error('name') is-invalid @enderror" autofocus>
            </x-ui.field>
            <x-ui.field label="Type" for="loc-type" error="type">
                <select id="loc-type" wire:model="type" class="rj-select">
                    @foreach ($types as $v => $label)<option value="{{ $v }}">{{ $label }}</option>@endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Order in lists" for="loc-order" error="sort_order">
                <input id="loc-order" type="number" min="0" wire:model="sort_order" class="rj-input tabular">
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
