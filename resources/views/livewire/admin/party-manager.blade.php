<div>
    <x-ui.page-header title="Karigars & centres" subtitle="The people and places work goes out to. Each has its own ledger."
        :crumbs="[['label' => 'Administration'], ['label' => 'Karigars & centres']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="plus" wire:click="create('hallmark_center')">New hallmarking centre</x-ui.button>
            <x-ui.button icon="plus" wire:click="create('karigar')">New karigar</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <x-ui.table :headers="['Name', 'Type', 'Phone', 'Address', '']">
            @forelse ($parties as $p)
                <tr wire:key="party-{{ $p->id }}">
                    <td class="font-semibold text-ink_text-primary">{{ $p->name }}</td>
                    <td><x-ui.badge size="sm" :tone="$p->type === 'karigar' ? 'gold' : 'dark'">{{ $types[$p->type] }}</x-ui.badge></td>
                    <td class="tabular">{{ $p->phone ?: '-' }}</td>
                    <td class="text-ink_text-secondary">{{ $p->address ?: '-' }}</td>
                    <td>
                        <div class="flex justify-end gap-1">
                            @if (\Illuminate\Support\Facades\Route::has('ledgers.party'))
                                <x-ui.button variant="secondary" size="sm" icon="book" :href="route('ledgers.party', $p)">Ledger</x-ui.button>
                            @endif
                            <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $p->id }})" aria-label="Edit {{ $p->name }}" />
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-ui.empty-state icon="user" title="No karigars or centres yet" message="Add the first one." compact /></td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit' : 'New'" icon="user" max-width="md" submit="save">
        <div class="space-y-4">
            <x-ui.field label="Type" for="pt-type" error="type">
                <select id="pt-type" wire:model="type" class="rj-select">
                    @foreach ($types as $v => $label)<option value="{{ $v }}">{{ $label }}</option>@endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Name" for="pt-name" error="name"><input id="pt-name" type="text" wire:model="name" maxlength="100" class="rj-input" autofocus></x-ui.field>
            <x-ui.field label="Phone" for="pt-phone" error="phone" optional><input id="pt-phone" type="text" wire:model="phone" maxlength="15" class="rj-input tabular"></x-ui.field>
            <x-ui.field label="Address" for="pt-addr" error="address" optional><input id="pt-addr" type="text" wire:model="address" maxlength="255" class="rj-input"></x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
