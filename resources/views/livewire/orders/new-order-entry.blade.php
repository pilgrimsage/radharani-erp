<div>
    <x-ui.page-header title="New Custom Order" subtitle="The customer, the product, then the advance."
        :crumbs="[['label' => 'Custom Orders', 'href' => route('orders.board')], ['label' => 'New Order']]" />

    <x-ui.stepper :steps="$steps" :current="$step" :reach="$reach" />

    <div class="max-w-[820px]">
        {{-- ============================================ 1. CUSTOMER --}}
        @if ($step === 1)
            <x-ui.card title="Who is it for" icon="user">
                @if ($customer)
                    <div class="flex items-center gap-3 p-4 rounded-xl bg-gold-tint/50 ring-1 ring-inset ring-gold-soft/60">
                        <span class="w-10 h-10 rounded-full gold-sheen text-white flex items-center justify-center font-semibold">{{ mb_substr($customer->name, 0, 1) }}</span>
                        <div class="flex-1"><div class="font-semibold text-ink_text-primary">{{ $customer->name }}</div><div class="text-[12.5px] text-ink_text-secondary tabular">{{ $customer->phone }}</div></div>
                        <x-ui.button variant="ghost" size="sm" wire:click="clearCustomer">Change</x-ui.button>
                    </div>
                @else
                    <div class="rj-input-icon"><x-ui.icon name="search" :size="16" /><input id="oe-customer" type="text" wire:model.live.debounce.250ms="customerSearch" placeholder="Search by name or phone" class="rj-input" autocomplete="off" autofocus></div>
                    @if ($customerResults->isNotEmpty())
                        <ul class="mt-2 rounded-xl border border-line-light divide-y divide-line-light overflow-hidden">
                            @foreach ($customerResults as $c)<li><button type="button" wire:click="chooseCustomer({{ $c->id }})" class="w-full text-left px-4 py-2.5 hover:bg-surface-sunken flex justify-between"><span class="font-semibold">{{ $c->name }}</span><span class="text-ink_text-muted tabular">{{ $c->phone }}</span></button></li>@endforeach
                        </ul>
                    @endif
                    <div class="mt-3"><x-ui.button variant="secondary" size="sm" icon="plus" wire:click="$toggle('addingCustomer')">New customer</x-ui.button></div>
                    @if ($addingCustomer)
                        <form wire:submit="saveNewCustomer" class="mt-4 p-4 rounded-xl ring-1 ring-inset ring-line-light grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-3 items-end">
                            <x-ui.field label="Name" for="oc-name" error="newName"><input id="oc-name" type="text" wire:model="newName" class="rj-input" maxlength="100"></x-ui.field>
                            <x-ui.field label="Mobile number" for="oc-phone" error="newPhone"><input id="oc-phone" type="text" wire:model="newPhone" class="rj-input tabular" inputmode="numeric"></x-ui.field>
                            <x-ui.button type="submit" icon="check" target="saveNewCustomer">Add</x-ui.button>
                        </form>
                    @endif
                @endif
                @error('customerId')<p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p>@enderror
            </x-ui.card>
        @endif

        {{-- ============================================ 2. PRODUCT --}}
        @if ($step === 2)
            <x-ui.card title="The product" icon="gem">
                <div class="space-y-5">
                    <x-ui.field label="Where will it come from" for="oe-src">
                        <select id="oe-src" wire:model.live="sourcing" class="rj-select">
                            @foreach ($sourcings as $k => [$label, $hint])<option value="{{ $k }}">{{ $label }}: {{ $hint }}</option>@endforeach
                        </select>
                        <p class="rj-help">Path: {{ collect($sourcings[$sourcing][2])->map(fn ($s) => ['karigar' => 'Karigar', 'hallmark' => 'Hallmarking', 'sales' => 'Sales'][$s])->implode(' → ') }}</p>
                    </x-ui.field>

                    @if ($sourcing === 'stock')
                        <div>
                            @if ($item)
                                <div class="flex items-center gap-3 p-3.5 rounded-xl bg-gold-tint/50 ring-1 ring-inset ring-gold-soft/60"><span class="rj-code">{{ $item->label }}</span><span class="text-[12.5px] text-ink_text-secondary flex-1">{{ $item->category }} · {{ number_format($item->weight, 3) }} g</span><x-ui.button variant="ghost" size="sm" wire:click="clearItem">Change</x-ui.button></div>
                                <p class="rj-help">Anyone selling it later will see a warning that it is held for this customer.</p>
                            @else
                                <div class="rj-input-icon"><x-ui.icon name="scan" :size="16" /><input type="text" wire:model.live.debounce.250ms="existingItemSearch" placeholder="HUID, code, packet or box" class="rj-input" aria-label="Find the piece" autocomplete="off"></div>
                                @if ($itemResults->isNotEmpty())
                                    <ul class="mt-2 rounded-xl border border-line-light divide-y divide-line-light overflow-hidden">@foreach ($itemResults as $i)<li><button type="button" wire:click="chooseItem({{ $i->id }})" class="w-full text-left px-4 py-2.5 hover:bg-surface-sunken"><span class="rj-code">{{ $i->label }}</span> <span class="text-ink_text-muted text-[12.5px]">{{ $i->category }} · {{ number_format($i->weight, 3) }} g</span></button></li>@endforeach</ul>
                                @endif
                            @endif
                            @error('existingItemId')<p class="rj-error">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <x-ui.field label="Description" for="oe-desc" error="productDescription"><input id="oe-desc" type="text" wire:model="productDescription" maxlength="200" class="rj-input @error('productDescription') is-invalid @enderror"></x-ui.field>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-ui.field label="Metal" for="oe-metal"><select id="oe-metal" wire:model.live="metal" class="rj-select"><option value="gold">Gold</option><option value="silver">Silver</option><option value="platinum">Platinum</option><option value="titanium">Titanium</option></select></x-ui.field>
                        <x-ui.field label="Subcategory" for="oe-cat" optional><select id="oe-cat" wire:model="category" class="rj-select"><option value="">Any</option>@foreach ($subcategories as $c)<option value="{{ $c->name }}">{{ $c->name }}</option>@endforeach</select></x-ui.field>
                        <x-ui.field label="Estimated weight (g)" for="oe-w" error="estimatedWeight" optional><input id="oe-w" type="number" step="0.001" min="0" wire:model="estimatedWeight" class="rj-input tabular"></x-ui.field>
                    </div>
                    <x-ui.field label="Expected ready by" for="oe-ready" error="expectedReadyDate" optional><input id="oe-ready" type="date" min="{{ today()->toDateString() }}" wire:model="expectedReadyDate" class="rj-input max-w-[220px]"></x-ui.field>

                    <x-ui.field label="Reference images" for="oe-img" error="images.*" optional hint="Pictures the customer brought. Compressed when saved.">
                        <input id="oe-img" type="file" wire:model="images" accept="image/*" multiple class="rj-input">
                        @if ($images)
                            <div class="flex flex-wrap gap-2 mt-3">
                                @foreach ($images as $i => $img)
                                    <div class="relative" wire:key="oi-{{ $i }}"><img src="{{ $img->temporaryUrl() }}" alt="" class="w-20 h-20 rounded-lg object-cover ring-1 ring-line">
                                        <button type="button" wire:click="removeImage({{ $i }})" class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-white shadow ring-1 ring-line flex items-center justify-center" aria-label="Remove image"><x-ui.icon name="x" :size="12" /></button></div>
                                @endforeach
                            </div>
                        @endif
                    </x-ui.field>
                </div>
            </x-ui.card>
        @endif

        {{-- ============================================ 3. ADVANCE --}}
        @if ($step === 3)
            <x-ui.card title="Advance" subtitle="What the customer pays now" icon="coins">
                <div class="space-y-5">
                    <label class="flex items-start gap-3 p-4 rounded-xl ring-1 ring-inset ring-line-light cursor-pointer">
                        <input type="checkbox" wire:model.live="fullPaymentNow" class="rj-checkbox mt-0.5">
                        <span><span class="block text-[13.5px] font-semibold">Full payment now</span><span class="block text-[12.5px] text-ink_text-secondary">The metal rate is locked to today. Otherwise the rate on the day of delivery applies.</span></span>
                    </label>
                    @if ($fullPaymentNow)
                        <x-ui.field label="Full value (₹)" for="oe-val" error="estimatedValue"><input id="oe-val" type="number" step="0.01" min="0" wire:model="estimatedValue" class="rj-input tabular max-w-[240px]"></x-ui.field>
                    @else
                        <x-ui.field label="Advance paid (₹)" for="oe-dep" error="depositAmount" optional><input id="oe-dep" type="number" step="0.01" min="0" wire:model="depositAmount" class="rj-input tabular max-w-[240px]"></x-ui.field>
                    @endif
                    <x-ui.button size="lg" icon="check" wire:click="submit" target="submit" class="w-full">Place order</x-ui.button>
                </div>
            </x-ui.card>
        @endif

        <div class="flex justify-between mt-5">
            <x-ui.button variant="secondary" icon="arrow-left" wire:click="goToStep({{ max(1, $step - 1) }})" :disabled="$step === 1">Back</x-ui.button>
            @if ($step < 3)<x-ui.button iconRight="arrow-right" wire:click="next" target="next">Continue</x-ui.button>@endif
        </div>
    </div>
</div>
