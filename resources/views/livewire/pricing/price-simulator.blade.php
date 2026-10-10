<div>
    <x-ui.page-header title="Price simulator" subtitle="Try a piece and see how its price is worked out. Nothing here is saved."
        :crumbs="[['label' => 'Pricing & Rates', 'href' => route('pricing.rates')], ['label' => 'Price simulator']]" />

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_420px] gap-6 items-start">
        <x-ui.card title="The piece" icon="gem">
            <div class="space-y-4">
                <x-ui.field label="Metal" for="ps-metal">
                    <select id="ps-metal" wire:model.live="metal" class="rj-select">
                        @foreach ($metals as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </x-ui.field>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.field label="Carat / purity" for="ps-purity">
                        <select id="ps-purity" wire:model.live="purity" class="rj-select">
                            @foreach ($purities as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Category" for="ps-cat" optional>
                        <select id="ps-cat" wire:model.live="categoryId" class="rj-select">
                            <option value="">Any</option>
                            @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                    </x-ui.field>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.field label="Weight (g)" for="ps-weight">
                        <input id="ps-weight" type="number" step="0.001" min="0" wire:model.live.debounce.300ms="weight" class="rj-input tabular">
                    </x-ui.field>
                    <x-ui.field label="Net weight (g)" for="ps-net" optional hint="Weight without stones. Leave empty to use the weight.">
                        <input id="ps-net" type="number" step="0.001" min="0" wire:model.live.debounce.300ms="net_weight" class="rj-input tabular">
                    </x-ui.field>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.field label="Making charge type" for="ps-mt">
                        <select id="ps-mt" wire:model.live="making_type" class="rj-select">
                            <option value="percentage">Percentage of metal value</option>
                            <option value="flat_per_gram">Flat amount per gram</option>
                            <option value="flat_per_piece">Flat amount per piece</option>
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Making charge value" for="ps-mv">
                        <input id="ps-mv" type="number" step="0.01" min="0" wire:model.live.debounce.300ms="making_value" class="rj-input tabular">
                    </x-ui.field>
                </div>
                <x-ui.field label="Stone value (₹)" for="ps-stone" optional>
                    <input id="ps-stone" type="number" step="0.01" min="0" wire:model.live.debounce.300ms="stone_value" class="rj-input tabular">
                </x-ui.field>
                <label class="flex items-center gap-2.5 text-[13px]"><input type="checkbox" wire:model.live="hallmarked" class="rj-checkbox"> Hallmarked (adds the hallmarking charge)</label>
            </div>
        </x-ui.card>

        <x-ui.card title="The price" icon="coins" class="xl:sticky xl:top-24">
            @if ($result)
                @if ($result['rate'] <= 0)
                    <div class="mb-3 px-3.5 py-2.5 rounded-control bg-warning-bg text-warning text-[12.5px]">No rate is set for {{ ucfirst($metal) }}, so the metal value is 0.</div>
                @endif
                <dl class="space-y-2.5 text-[13.5px]">
                    <div class="flex justify-between"><dt class="text-ink_text-secondary">{{ ucfirst($metal) }} rate today</dt><dd class="tabular">₹{{ number_format($result['rate'], 2) }} / g</dd></div>
                    <div class="flex justify-between"><dt class="text-ink_text-secondary">Weight charged</dt><dd class="tabular">{{ number_format($result['weight'], 3) }} g</dd></div>
                    <div class="flex justify-between font-semibold"><dt>Metal value</dt><dd class="tabular">₹{{ number_format($result['metal_value'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink_text-secondary">Making charge</dt><dd class="tabular">₹{{ number_format($result['making'], 2) }}</dd></div>
                    @if ($result['stone_value'] > 0)<div class="flex justify-between"><dt class="text-ink_text-secondary">Stones</dt><dd class="tabular">₹{{ number_format($result['stone_value'], 2) }}</dd></div>@endif
                    <div class="flex justify-between"><dt class="text-ink_text-secondary">Hallmarking charge</dt><dd class="tabular">₹{{ number_format($result['huid_charge'], 2) }}</dd></div>
                    @if ($result['discount'] > 0)<div class="flex justify-between text-success"><dt>Discount</dt><dd class="tabular">- ₹{{ number_format($result['discount'], 2) }}</dd></div>@endif
                    <div class="flex justify-between pt-3 border-t border-line-light font-display text-[26px] font-semibold"><dt>Total</dt><dd class="tabular">₹{{ number_format($result['total'], 2) }}</dd></div>
                </dl>
                <p class="rj-help mt-4">Provisional: this follows the current pricing rules and will change when the rule bands and per-carat rates are in.</p>
            @else
                <x-ui.empty-state icon="scale" title="Enter a weight" message="The price shows here as you type." compact />
            @endif
        </x-ui.card>
    </div>
</div>
