<div>
    <x-ui.page-header title="Categories" subtitle="Metal first, then subcategory. The same subcategory name can sit under different metals."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Categories']]">
        <x-slot:actions>
            <x-ui.button icon="plus" wire:click="create">New subcategory</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach ($tree as $node)
            <x-ui.card :padding="false" :title="$node['label']" :subtitle="$node['rows']->count() . ' ' . \Illuminate\Support\Str::plural('subcategory', $node['rows']->count())" icon="layers">
                <x-slot:actions>
                    <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="create('{{ $node['metal'] }}')">Add</x-ui.button>
                </x-slot:actions>
                <ul class="divide-y divide-line-light">
                    @forelse ($node['rows'] as $c)
                        <li wire:key="cat-{{ $c->id }}" class="flex items-center gap-3 px-5 py-3 {{ $c->is_active ? '' : 'opacity-60' }}">
                            <span class="flex-1 min-w-0 font-semibold text-ink_text-primary truncate">{{ $c->name }}</span>
                            <span class="text-[12px] text-ink_text-muted tabular">{{ $c->items_count }} {{ \Illuminate\Support\Str::plural('piece', $c->items_count) }}</span>
                            @unless ($c->is_active)<x-ui.badge size="sm">Off</x-ui.badge>@endunless
                            <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $c->id }})" aria-label="Edit {{ $c->name }}" />
                            <x-ui.button variant="secondary" size="xs" wire:click="toggle({{ $c->id }})">{{ $c->is_active ? 'Switch off' : 'Switch on' }}</x-ui.button>
                        </li>
                    @empty
                        <li><x-ui.empty-state icon="layers" title="No subcategories" message="Add the first one for this metal." compact /></li>
                    @endforelse
                </ul>
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit subcategory' : 'New subcategory'" icon="layers" max-width="md" submit="save">
        <div class="space-y-4">
            <x-ui.field label="Metal" for="cat-metal" error="metal">
                <select id="cat-metal" wire:model="metal" class="rj-select">
                    @foreach ($metals as $v => $label)<option value="{{ $v }}">{{ $label }}</option>@endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Subcategory" for="cat-name" error="name" hint="For example Earrings, Bangles or Sakha. Renaming updates every piece in it.">
                <input id="cat-name" type="text" wire:model="name" maxlength="50" class="rj-input @error('name') is-invalid @enderror" autofocus>
            </x-ui.field>
            <x-ui.field label="Order in lists" for="cat-order" error="sort_order">
                <input id="cat-order" type="number" min="0" wire:model="sort_order" class="rj-input tabular">
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
