<div>
    <x-ui.page-header title="Pricing rules" subtitle="Making charges, additional charges, discounts and hallmarking charges. The most specific rule wins: product, then category, then price range, then metal."
        :crumbs="[['label' => 'Pricing & Rates', 'href' => route('pricing.rates')], ['label' => 'Pricing rules']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="scale" :href="route('pricing.simulator')">Price simulator</x-ui.button>
            @if ($kind !== 'categories')<x-ui.button icon="plus" wire:click="create">New rule</x-ui.button>@endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rj-segment mb-6 flex-wrap h-auto" role="tablist">
        @foreach ($kinds as $k => $label)
            <button type="button" role="tab" wire:click="setKind('{{ $k }}')" class="{{ $kind === $k ? 'is-active' : '' }}">{{ $label }}</button>
        @endforeach
        <button type="button" role="tab" wire:click="setKind('categories')" class="{{ $kind === 'categories' ? 'is-active' : '' }}">Making charges by category</button>
    </div>

    @if ($kind === 'categories')
        <p class="text-[13px] text-ink_text-secondary mb-4">The making charge of each subcategory, for every carat. Anything left empty has none yet. A piece with its own making charge, or a more specific rule, still wins.</p>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach ($tree as $metal => $cats)
                <x-ui.card :padding="false" :title="ucfirst($metal)" icon="layers">
                    <ul class="divide-y divide-line-light">
                        @foreach ($cats as $c)
                            <li class="flex flex-wrap items-center gap-3 px-5 py-3" wire:key="cm-{{ $c->id }}">
                                <span class="flex-1 min-w-[120px] font-semibold text-ink_text-primary">{{ $c->name }}
                                    @if ($categoryMaking[$c->id]['value'] === '')<x-ui.badge tone="warning" size="sm">Not set</x-ui.badge>@endif</span>
                                <select wire:model="categoryMaking.{{ $c->id }}.calc" class="rj-select w-auto" aria-label="Type for {{ $c->name }}">
                                    @foreach ($calcLabels as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                </select>
                                <input type="number" step="0.01" min="0" wire:model="categoryMaking.{{ $c->id }}.value" class="rj-input tabular w-[110px] @error('categoryMaking.' . $c->id . '.value') is-invalid @enderror" aria-label="Value for {{ $c->name }}" placeholder="Value">
                                <x-ui.button size="sm" variant="secondary" wire:click="saveCategoryMaking({{ $c->id }})" target="saveCategoryMaking">Save</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endforeach
            @if ($tree->isEmpty())<x-ui.empty-state icon="layers" title="No categories" message="Add subcategories under Stock, Categories." />@endif
        </div>
    @else
        <x-ui.card :padding="false">
            <x-ui.table :headers="['Applies to', 'Amount', 'Valid', 'Status', '']">
                @forelse ($rules as $r)
                    <tr wire:key="pr-{{ $r->id }}" class="{{ $r->active ? '' : 'opacity-60' }}">
                        <td>
                            <span class="font-semibold text-ink_text-primary">{{ $r->target() }}</span>
                            @if ($r->name)<span class="block text-[12.5px] text-ink_text-muted">{{ $r->name }}</span>@endif
                        </td>
                        <td class="tabular font-semibold">{{ $r->describe() }}</td>
                        <td class="text-[12.5px] text-ink_text-secondary">{{ $r->valid_from || $r->valid_to ? ($r->valid_from?->format('j M Y') ?? 'now') . ' to ' . ($r->valid_to?->format('j M Y') ?? 'open') : 'Always' }}</td>
                        <td><x-ui.badge size="sm" :tone="$r->active ? 'success' : 'neutral'">{{ $r->active ? 'On' : 'Off' }}</x-ui.badge></td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $r->id }})" aria-label="Edit rule" />
                                <x-ui.button variant="secondary" size="xs" wire:click="toggle({{ $r->id }})">{{ $r->active ? 'Switch off' : 'Switch on' }}</x-ui.button>
                                <x-ui.button variant="ghost" size="icon-sm" icon="trash" aria-label="Remove rule"
                                    x-on:click="$dispatch('rj-confirm', { title: 'Remove this rule?', message: 'It stops applying straight away. The change stays in the audit log.', confirm: 'Remove', tone: 'danger', action: () => $wire.remove({{ $r->id }}) })" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty-state icon="coins" title="No {{ strtolower($kinds[$kind]) }} rules" message="Add one, for a product, a category or a price range." compact /></td></tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>
    @endif

    <x-ui.modal wire:model="showForm" :title="($editingId ? 'Edit ' : 'New ') . strtolower($kinds[$kind] ?? 'rule')" icon="coins" max-width="lg" submit="save">
        <div class="space-y-4">
            <x-ui.field label="Applies to" for="pr-scope" error="scope">
                <select id="pr-scope" wire:model.live="scope" class="rj-select">
                    @foreach ($scopes as $s)<option value="{{ $s }}">{{ $scopeLabels[$s] }}</option>@endforeach
                </select>
            </x-ui.field>

            @if ($scope === 'product')
                <x-ui.field label="Piece" for="pr-prod" error="productCode" hint="Its HUID or internal code."><input id="pr-prod" type="text" wire:model="productCode" class="rj-input rj-code" autocomplete="off"></x-ui.field>
            @elseif ($scope === 'category')
                <x-ui.field label="Category" for="pr-cat" error="category">
                    <select id="pr-cat" wire:model="category" class="rj-select"><option value="">Choose...</option>
                        @foreach ($categoryNames->unique('name') as $c)<option value="{{ $c->name }}">{{ $c->name }}</option>@endforeach</select>
                </x-ui.field>
            @elseif ($scope === 'price_range')
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.field label="Metal value from (₹)" for="pr-min" error="minValue" optional><input id="pr-min" type="number" step="1" min="0" wire:model="minValue" class="rj-input tabular"></x-ui.field>
                    <x-ui.field label="Up to (₹)" for="pr-max" error="maxValue" optional><input id="pr-max" type="number" step="1" min="0" wire:model="maxValue" class="rj-input tabular"></x-ui.field>
                </div>
                <p class="rj-help -mt-2">The price range is the metal value alone (net weight times the carat's rate), never the final price. The same piece can move to another range when the rate changes.</p>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.field label="Metal" for="pr-metal" error="metal" :optional="$scope !== 'metal'">
                    <select id="pr-metal" wire:model="metal" class="rj-select"><option value="">Any</option>@foreach ($metals as $m)<option value="{{ $m }}">{{ ucfirst($m) }}</option>@endforeach</select>
                </x-ui.field>
                <x-ui.field label="Carat" for="pr-carat" error="purity" optional>
                    <select id="pr-carat" wire:model="purity" class="rj-select"><option value="">Any</option>@foreach ($carats as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select>
                </x-ui.field>
            </div>

            @if ($kind === 'additional')
                <x-ui.field label="What it is" for="pr-name" error="name" hint="For example Packaging or Certificate."><input id="pr-name" type="text" wire:model="name" maxlength="60" class="rj-input"></x-ui.field>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.field label="Charged as" for="pr-calc" error="calc">
                    <select id="pr-calc" wire:model="calc" class="rj-select">@foreach ($calcLabels as $v => $l)<option value="{{ $v }}">{{ $l }}{{ $v === 'percentage' ? ($kind === 'discount' ? ' of the price' : ' of the metal value') : '' }}</option>@endforeach</select>
                </x-ui.field>
                <x-ui.field label="Value" for="pr-value" error="value"><input id="pr-value" type="number" step="0.01" min="0" wire:model="value" class="rj-input tabular"></x-ui.field>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.field label="Valid from" for="pr-from" error="validFrom" optional><input id="pr-from" type="date" wire:model="validFrom" class="rj-input"></x-ui.field>
                <x-ui.field label="Valid until" for="pr-to" error="validTo" optional><input id="pr-to" type="date" wire:model="validTo" class="rj-input"></x-ui.field>
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">Save rule</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
