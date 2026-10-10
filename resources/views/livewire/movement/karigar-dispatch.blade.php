<div>
    <x-ui.page-header title="Karigar Dispatch" subtitle="Pick what is being sent. The form only asks what that situation needs."
        :crumbs="[['label' => 'Movements'], ['label' => 'Karigar Dispatch']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="corner-down-right" :href="route('movements.karigar-return')">Record a return</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Which situation --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        @foreach ([
            'tagged' => ['gem', 'A finished piece for repair', 'A tagged piece from stock. The same piece comes back.'],
            'customer_material' => ['user', "A customer's own gold", 'Brought in by a customer for repair. Never part of shop stock.'],
            'raw_material' => ['flame', 'Raw material for a new piece', 'Metal handed over by weight. A new piece comes back.'],
        ] as $key => [$icon, $title, $text])
            @php $active = $situation === $key; @endphp
            <button type="button" wire:click="setSituation('{{ $key }}')"
                @class([
                    'press relative text-left rounded-card p-5 flex items-start gap-4 border transition-[border-color,box-shadow,background-color] duration-200',
                    'bg-gold-tint border-gold shadow-focus' => $active,
                    'bg-white border-line-light shadow-card hover:border-gold-soft hover:shadow-raised' => ! $active,
                ])>
                <span @class([
                    'w-11 h-11 shrink-0 rounded-xl flex items-center justify-center',
                    'gold-sheen text-ink shadow-gold' => $active,
                    'bg-surface-muted text-ink_text-secondary' => ! $active,
                ])><x-ui.icon :name="$icon" :size="19" /></span>
                <span class="min-w-0 pr-5">
                    <span class="block text-[14.5px] font-bold text-ink_text-primary">{{ $title }}</span>
                    <span class="block text-[12.5px] text-ink_text-secondary mt-0.5">{{ $text }}</span>
                </span>
                @if ($active)
                    <span class="absolute top-4 right-4 w-5 h-5 rounded-full bg-gold text-white flex items-center justify-center"><x-ui.icon name="check" :size="12" /></span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_400px] gap-6 items-start">
        <x-ui.card :padding="false"
            :title="['tagged' => 'Send pieces for work', 'customer_material' => 'Send a customer\'s material', 'raw_material' => 'Issue raw material'][$situation]"
            :subtitle="['tagged' => 'Every piece is marked as out until it comes back.', 'customer_material' => 'Recorded against the customer, not against stock.', 'raw_material' => 'The new piece gets its tag when it comes back.'][$situation]"
            icon="truck">
            <form wire:submit="submit">
                <div class="p-5 sm:p-6 space-y-6">
                    {{-- ---------------------------------------------------- tagged --}}
                    @if ($situation === 'tagged')
                        <x-movement.item-picker :items="$basketItems" :results="$pickResults" label="Pieces going out" />

                        <x-ui.field label="Work to be done" error="work">
                            <div class="flex flex-wrap gap-2">
                                @foreach (\App\Livewire\Movement\KarigarDispatch::WORK as $w)
                                    <button type="button" wire:click="$set('work', '{{ $w }}')"
                                        class="h-8 px-3 rounded-lg text-[12.5px] font-semibold ring-1 ring-inset transition-colors
                                        {{ $work === $w ? 'bg-ink text-gold-light ring-ink' : 'bg-white text-ink_text-secondary ring-line hover:ring-line-strong' }}">{{ $w }}</button>
                                @endforeach
                                @php $customWork = in_array($work, \App\Livewire\Movement\KarigarDispatch::WORK, true) ? '' : $work; @endphp
                                <input type="text" value="{{ $customWork }}" maxlength="50" aria-label="Other work"
                                    x-on:change="$event.target.value.trim() ? $wire.set('work', $event.target.value.trim()) : $wire.set('work', 'Repair')"
                                    class="rj-input rj-input-sm w-[170px] {{ $customWork ? 'border-gold bg-gold-tint' : '' }}" placeholder="Something else">
                            </div>
                        </x-ui.field>
                    @endif

                    {{-- ---------------------------------------- customer_material --}}
                    @if ($situation === 'customer_material')
                        <x-ui.field label="Customer" error="customerId">
                            @if ($customer)
                                <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-surface-sunken ring-1 ring-inset ring-line-light">
                                    <span class="w-9 h-9 shrink-0 rounded-full bg-white ring-1 ring-gold/40 text-gold-dark flex items-center justify-center font-display text-[17px] font-semibold">{{ strtoupper(mb_substr($customer->name, 0, 1)) }}</span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[13.5px] font-semibold text-ink_text-primary truncate">{{ $customer->name }}</div>
                                        <div class="text-[12.5px] text-ink_text-muted tabular">{{ $customer->phone }}</div>
                                    </div>
                                    <x-ui.button variant="ghost" size="sm" wire:click="clearCustomer">Change</x-ui.button>
                                </div>
                            @else
                                <div class="relative">
                                    <x-ui.search-input wire:model.live.debounce.300ms="customerSearch" placeholder="Name or phone number" />
                                    @if ($customerResults->isNotEmpty())
                                        <div class="absolute left-0 right-0 top-full mt-1.5 z-dropdown bg-white rounded-control ring-1 ring-line shadow-pop overflow-hidden">
                                            @foreach ($customerResults as $c)
                                                <button type="button" wire:key="cust-{{ $c->id }}" wire:click="chooseCustomer({{ $c->id }})"
                                                    class="w-full flex items-center justify-between gap-3 px-3.5 py-2.5 text-left border-b border-line-light last:border-0 hover:bg-gold-tint/60">
                                                    <span class="text-[13px] font-semibold text-ink_text-primary">{{ $c->name }}</span>
                                                    <span class="text-[12.5px] text-ink_text-muted tabular">{{ $c->phone }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @elseif (trim($customerSearch) !== '')
                                        <p class="rj-help">No customer matches. Add them under Administration, Customers first.</p>
                                    @endif
                                </div>
                            @endif
                        </x-ui.field>

                        <x-ui.field label="What is it" for="kd-desc" error="description" hint="As the customer would describe it, e.g. broken gold chain, 2 pieces">
                            <input id="kd-desc" type="text" wire:model="description" maxlength="150" class="rj-input @error('description') is-invalid @enderror">
                        </x-ui.field>
                    @endif

                    {{-- --------------------------- metal + weight (customer / raw) --}}
                    @if ($situation !== 'tagged')
                        <x-ui.field label="Metal" error="metal">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                @foreach (\App\Livewire\Movement\KarigarDispatch::METALS as $k => $v)
                                    <label class="relative flex items-center gap-2.5 h-11 px-3 rounded-control border cursor-pointer transition-colors
                                        {{ $metal === $k ? 'border-gold bg-gold-tint shadow-focus' : 'border-line hover:border-line-strong' }}">
                                        <input type="radio" wire:model.live="metal" value="{{ $k }}" class="sr-only">
                                        <x-movement.metal-dot :metal="$k" size="lg" />
                                        <span class="text-[13px] font-semibold {{ $metal === $k ? 'text-ink_text-primary' : 'text-ink_text-secondary' }}">{{ $v }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </x-ui.field>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-ui.field label="Weight handed over" for="kd-weight" error="weight">
                                <div class="relative">
                                    <input id="kd-weight" type="number" step="0.001" min="0" wire:model="weight" class="rj-input pr-9 tabular @error('weight') is-invalid @enderror">
                                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[13px] text-ink_text-muted pointer-events-none">g</span>
                                </div>
                            </x-ui.field>
                            @if ($situation === 'raw_material')
                                <x-ui.field label="Purity" for="kd-purity" error="purity" optional>
                                    <input id="kd-purity" type="text" wire:model="purity" maxlength="10" class="rj-input" placeholder="{{ $purities[0] ?? '' }}">
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        @foreach ($purities as $p)
                                            <button type="button" wire:click="$set('purity', '{{ $p }}')"
                                                class="h-7 px-2.5 rounded-md text-[12px] font-semibold ring-1 ring-inset transition-colors
                                                {{ $purity === $p ? 'bg-ink text-gold-light ring-ink' : 'bg-white text-ink_text-secondary ring-line hover:ring-line-strong' }}">{{ $p }}</button>
                                        @endforeach
                                    </div>
                                </x-ui.field>
                            @endif
                        </div>

                        @if ($situation === 'raw_material')
                            <x-ui.field label="What is to be made" for="kd-make" error="description" optional hint="e.g. pair of jhumkas, 22K chain">
                                <input id="kd-make" type="text" wire:model="description" maxlength="50" class="rj-input">
                            </x-ui.field>
                        @endif
                    @endif

                    {{-- ---------------------------------------------- common fields --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-6 border-t border-line-light">
                        <x-ui.field label="Karigar" for="kd-vendor" error="vendorId">
                            <select id="kd-vendor" wire:model="vendorId" class="rj-select @error('vendorId') is-invalid @enderror">
                                <option value="">Choose a karigar</option>
                                @foreach ($karigars as $k)
                                    <option value="{{ $k->id }}">{{ $k->name }}{{ $k->phone ? ' · ' . $k->phone : '' }}</option>
                                @endforeach
                            </select>
                            @if ($karigars->isEmpty())
                                <p class="rj-help">No karigars yet. Add one under Purchases, Vendors.</p>
                            @endif
                        </x-ui.field>
                        <x-ui.field label="Expected back" for="kd-due" error="expectedReturn">
                            <input id="kd-due" type="date" wire:model.live="expectedReturn" class="rj-input tabular">
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                @foreach ([3, 7, 15, 30] as $d)
                                    @php $val = today()->addDays($d)->toDateString(); @endphp
                                    <button type="button" wire:click="$set('expectedReturn', '{{ $val }}')"
                                        class="h-7 px-2.5 rounded-md text-[12px] font-semibold ring-1 ring-inset transition-colors
                                        {{ $expectedReturn === $val ? 'bg-ink text-gold-light ring-ink' : 'bg-white text-ink_text-secondary ring-line hover:ring-line-strong' }}">{{ $d }} days</button>
                                @endforeach
                            </div>
                        </x-ui.field>
                    </div>

                    <x-movement.done-by />

                    <x-ui.field label="Note" for="kd-note" error="note" optional>
                        <input id="kd-note" type="text" wire:model="note" maxlength="255" class="rj-input" placeholder="Anything the next person should know">
                    </x-ui.field>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 bg-surface-sunken border-t border-line-light rounded-b-card">
                    <p class="text-[12.5px] text-ink_text-secondary">
                        @if ($situation === 'tagged')
                            {{ $basketItems->count() }} {{ \Illuminate\Support\Str::plural('piece', $basketItems->count()) }}, marked as out until returned.
                        @elseif ($situation === 'customer_material')
                            Tracked under the customer until it comes back.
                        @else
                            Weighed out now, weighed back at return.
                        @endif
                    </p>
                    <x-ui.button type="submit" size="lg" icon="truck" target="submit">Confirm dispatch</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        {{-- Out with karigars --}}
        <x-ui.card :padding="false" title="Out with karigars" :subtitle="$out->count() . ' open' . ($overdue ? ', ' . $overdue . ' overdue' : '')" icon="clock" class="xl:sticky xl:top-24">
            <ul class="divide-y divide-line-light max-h-[640px] overflow-y-auto">
                @forelse ($out as $r)
                    <li class="flex items-start gap-3 px-5 py-3.5">
                        <span class="w-8 h-8 shrink-0 rounded-lg bg-surface-muted text-ink_text-secondary flex items-center justify-center mt-0.5">
                            <x-ui.icon :name="$r['icon']" :size="14" />
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="{{ $r['kind'] === 'Customer' ? 'text-[13px] font-semibold' : 'rj-code' }} text-ink_text-primary truncate">{{ $r['code'] }}</span>
                                <x-movement.due :date="$r['due']" />
                            </div>
                            <div class="text-[12px] text-ink_text-muted truncate">{{ $r['detail'] }}</div>
                            <div class="text-[12px] text-ink_text-secondary mt-0.5">
                                {{ $r['karigar'] ?: 'Unknown karigar' }} · <span class="tabular">{{ number_format((float) $r['weight'], 3) }} g</span> · {{ $r['since']->format('j M') }}
                            </div>
                        </div>
                    </li>
                @empty
                    <li><x-ui.empty-state icon="check-circle" title="Nothing is out" message="Everything sent to a karigar has come back." compact /></li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>
</div>
