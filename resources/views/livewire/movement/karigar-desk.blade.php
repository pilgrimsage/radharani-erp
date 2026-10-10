<div>
    <x-ui.page-header title="Karigar" subtitle="Issue work, receive it back in as many parts as it takes, and pay in cash or metal."
        :crumbs="[['label' => 'Movements'], ['label' => 'Karigar']]">
        <x-slot:actions>
            @if (\Illuminate\Support\Facades\Route::has('ledgers.index'))
                <x-ui.button variant="secondary" icon="book" :href="route('ledgers.index', ['kind' => 'karigar'])">Karigar ledgers</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rj-segment mb-6" role="tablist">
        @foreach (['issue' => 'Issue', 'receive' => 'Receive (' . $open->count() . ' open)', 'payments' => 'Payments', 'batches' => 'All batches', 'repairs' => 'Repairs & customer metal'] as $k => $label)
            <button type="button" role="tab" wire:click="setTab('{{ $k }}')" class="{{ $tab === $k ? 'is-active' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ===================================================== ISSUE --}}
    @if ($tab === 'issue')
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
            <x-ui.card title="Issue a batch" subtitle="Identified by the date and time you save it" icon="truck">
                <form wire:submit="issue" class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-ui.field label="Karigar" for="ki-vendor" error="vendorId">
                            <select id="ki-vendor" wire:model="vendorId" class="rj-select @error('vendorId') is-invalid @enderror">
                                <option value="">Choose...</option>
                                @foreach ($karigars as $k)<option value="{{ $k->id }}">{{ $k->name }}</option>@endforeach
                            </select>
                        </x-ui.field>
                        <x-ui.field label="Metal" for="ki-metal" error="metal">
                            <select id="ki-metal" wire:model.live="metal" class="rj-select">
                                @foreach ($metals as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                            </select>
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Description" for="ki-desc" error="description" hint="What is being made, for example bangles for the Diwali range.">
                        <input id="ki-desc" type="text" wire:model="description" maxlength="150" class="rj-input @error('description') is-invalid @enderror">
                    </x-ui.field>

                    <x-ui.field label="Categories in this batch" optional hint="One batch can hold several.">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($subcategories->where('metal', $metal) as $c)
                                <button type="button" wire:click="toggleCategory('{{ $c->name }}')" wire:key="kc-{{ $c->id }}"
                                    class="h-8 px-3 rounded-md text-[12.5px] font-semibold ring-1 ring-inset transition-colors {{ in_array($c->name, $categories, true) ? 'bg-ink text-gold-light ring-ink' : 'bg-white text-ink_text-secondary ring-line hover:ring-line-strong' }}">{{ $c->name }}</button>
                            @endforeach
                            @if ($subcategories->where('metal', $metal)->isEmpty())<span class="text-[12.5px] text-ink_text-muted">No subcategories for this metal yet.</span>@endif
                        </div>
                    </x-ui.field>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-ui.field label="Pieces expected" for="ki-pieces" error="piecesExpected">
                            <input id="ki-pieces" type="number" min="1" wire:model="piecesExpected" class="rj-input tabular @error('piecesExpected') is-invalid @enderror">
                        </x-ui.field>
                        <x-ui.field label="Estimated weight (g)" for="ki-weight" error="estimatedWeight">
                            <input id="ki-weight" type="number" step="0.001" min="0" wire:model="estimatedWeight" class="rj-input tabular @error('estimatedWeight') is-invalid @enderror">
                        </x-ui.field>
                        <x-ui.field label="Expected back" for="ki-date" error="expectedReturn">
                            <input id="ki-date" type="date" min="{{ today()->toDateString() }}" wire:model="expectedReturn" class="rj-input">
                        </x-ui.field>
                    </div>

                    <div class="rounded-xl ring-1 ring-inset ring-line-light p-4">
                        <label class="flex items-center gap-2.5 text-[13.5px] font-semibold"><input type="checkbox" wire:model.live="withAdvance" class="rj-checkbox"> With an advance</label>
                        @if ($withAdvance)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                                <x-ui.field label="Cash (₹)" for="ki-cash" error="advanceCash" optional>
                                    <input id="ki-cash" type="number" step="0.01" min="0" wire:model="advanceCash" class="rj-input tabular @error('advanceCash') is-invalid @enderror">
                                </x-ui.field>
                                <x-ui.field label="Metal (g)" for="ki-am" error="advanceMetalWeight" optional>
                                    <input id="ki-am" type="number" step="0.001" min="0" wire:model="advanceMetalWeight" class="rj-input tabular @error('advanceMetalWeight') is-invalid @enderror">
                                </x-ui.field>
                                <x-ui.field label="Carat" for="ki-ap" error="advanceMetalPurity">
                                    <select id="ki-ap" wire:model="advanceMetalPurity" class="rj-select">
                                        @foreach ($purities[$metal] ?? [] as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
                                    </select>
                                </x-ui.field>
                            </div>
                            <p class="rj-help mt-2">Metal comes off the raw-metal balance. Cash is only recorded here.</p>
                        @endif
                    </div>

                    <x-ui.field label="For a custom order" for="ki-order" optional hint="Linking an order puts this batch on that order's path.">
                        <select id="ki-order" wire:model="orderId" class="rj-select">
                            <option value="">Not for an order</option>
                            @foreach ($orders as $o)<option value="{{ $o->id }}">#{{ $o->id }} · {{ $o->customer?->name }} · {{ \Illuminate\Support\Str::limit($o->product_description, 40) }}</option>@endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field label="Comments" for="ki-note" optional><input id="ki-note" type="text" wire:model="note" maxlength="255" class="rj-input"></x-ui.field>
                    <x-movement.photo-upload :photo="$photo" required label="Photo of what is going out" />
                    <x-movement.done-by />

                    <x-ui.button type="submit" size="lg" icon="truck" target="issue" class="w-full">Issue batch and print the report</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card title="Raw-metal balance" subtitle="What is available to give or pay" icon="scale" class="xl:sticky xl:top-24">
                @forelse ($balances as $b)
                    <div class="flex justify-between py-2 border-b border-line-light last:border-0 text-[13.5px]"><span>{{ ucfirst($b->metal) }} {{ $b->purity }}</span><span class="tabular font-semibold {{ $b->weight < 0 ? 'text-danger' : '' }}">{{ number_format($b->weight, 3) }} g</span></div>
                @empty
                    <p class="text-[13px] text-ink_text-muted">Nothing yet. Raw-material purchases add to this balance.</p>
                @endforelse
            </x-ui.card>
        </div>
    @endif

    {{-- ===================================================== RECEIVE --}}
    @if ($tab === 'receive')
        <div class="grid grid-cols-1 xl:grid-cols-[360px_minmax(0,1fr)] gap-6 items-start">
            <x-ui.card :padding="false" title="Open batches" :subtitle="$open->count() . ' with pieces still out'" icon="clock">
                <ul class="divide-y divide-line-light max-h-[640px] overflow-y-auto">
                    @forelse ($open as $b)
                        <li wire:key="ob-{{ $b->id }}">
                            <button type="button" wire:click="selectBatch({{ $b->id }})" class="w-full text-left px-5 py-3.5 hover:bg-surface-sunken {{ $batchId === $b->id ? 'bg-gold-tint/50' : '' }}">
                                <div class="flex items-baseline justify-between gap-2"><span class="font-semibold text-ink_text-primary">{{ $b->vendor->name }}</span>
                                    <span class="text-[12px] tabular {{ $b->expected_return && $b->expected_return->isPast() && ! $b->expected_return->isToday() ? 'text-danger font-semibold' : 'text-ink_text-muted' }}">{{ $b->expected_return?->format('j M') ?? 'no date' }}</span></div>
                                <div class="text-[12.5px] text-ink_text-secondary truncate">{{ $b->label }} · {{ $b->description }}</div>
                                <div class="text-[12.5px] mt-1"><span class="font-semibold text-warning">{{ $b->pieces_pending }} pending</span> <span class="text-ink_text-muted">of {{ $b->pieces_expected }}</span></div>
                            </button>
                        </li>
                    @empty
                        <li><x-ui.empty-state icon="check-circle" title="Nothing out" message="Every batch is back or closed." compact /></li>
                    @endforelse
                </ul>
            </x-ui.card>

            @if ($batch)
                <x-ui.card :title="'Receive from ' . $batch->vendor->name" :subtitle="'Batch ' . $batch->label . ' · ' . $batch->pieces_received . ' of ' . $batch->pieces_expected . ' received, ' . $batch->pieces_pending . ' pending'" icon="package">
                    <form wire:submit="receive" class="space-y-5">
                        <div class="rj-segment">
                            <button type="button" wire:click="$set('disposition', 'stock')" class="{{ $disposition === 'stock' ? 'is-active' : '' }}">Add to stock</button>
                            <button type="button" wire:click="$set('disposition', 'hallmark')" class="{{ $disposition === 'hallmark' ? 'is-active' : '' }}">Send for hallmarking</button>
                        </div>

                        @if ($disposition === 'stock')
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <x-ui.field label="Subcategory" for="kr-cat" error="receiveCategoryId">
                                    <select id="kr-cat" wire:model="receiveCategoryId" class="rj-select @error('receiveCategoryId') is-invalid @enderror">
                                        <option value="">Choose...</option>
                                        @foreach ($receiveCategories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                    </select>
                                </x-ui.field>
                                <x-ui.field label="Carat / purity" for="kr-pur" error="receivePurity">
                                    <input id="kr-pur" type="text" wire:model="receivePurity" maxlength="10" class="rj-input">
                                </x-ui.field>
                            </div>
                            <x-ui.field label="Weight of each piece (g)" for="kr-weights" error="weights" hint="One per piece, separated by spaces or new lines. Each piece gets an internal code and waits in Pending Review.">
                                <textarea id="kr-weights" rows="4" wire:model.live.debounce.400ms="weights" class="rj-textarea tabular @error('weights') is-invalid @enderror" placeholder="4.25  4.31  3.98"></textarea>
                            </x-ui.field>
                            @php $n = count(array_filter(preg_split('/[\s,;]+/', trim($weights)))); @endphp
                            @if ($n)<p class="text-[12.5px] text-ink_text-secondary -mt-3">{{ $n }} {{ \Illuminate\Support\Str::plural('piece', $n) }}</p>@endif
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <x-ui.field label="Pieces" for="kr-hp" error="hallmarkPieces"><input id="kr-hp" type="number" min="1" wire:model="hallmarkPieces" class="rj-input tabular"></x-ui.field>
                                <x-ui.field label="Total weight (g)" for="kr-hw" error="hallmarkWeight"><input id="kr-hw" type="number" step="0.001" min="0" wire:model="hallmarkWeight" class="rj-input tabular"></x-ui.field>
                            </div>
                            <p class="rj-help -mt-2">They go to hallmarking untagged and are not made into pieces until they come back.</p>
                        @endif

                        <x-ui.field label="Weight loss (g)" for="kr-loss" error="lossWeight" optional hint="Typed in by you, never worked out.">
                            <input id="kr-loss" type="number" step="0.001" min="0" wire:model="lossWeight" class="rj-input tabular">
                        </x-ui.field>
                        <x-ui.field label="Note" for="kr-note" optional><input id="kr-note" type="text" wire:model="receiveNote" maxlength="255" class="rj-input"></x-ui.field>
                        <x-movement.photo-upload :photo="$photo" required label="Photo of what came back" />
                        <x-movement.done-by />
                        <x-ui.button type="submit" size="lg" icon="check" target="receive" class="w-full">Receive</x-ui.button>
                    </form>

                    @if ($canClose)
                        <div class="mt-6 pt-5 border-t border-line-light">
                            <h3 class="text-[13px] font-bold mb-2">Close the remaining {{ $batch->pieces_pending }}</h3>
                            <div class="flex gap-2">
                                <input type="text" wire:model="closeNote" maxlength="255" placeholder="Why the rest will not come back" class="rj-input flex-1" aria-label="Close note">
                                <x-ui.button variant="danger-soft" wire:click="closeRemainder" target="closeRemainder">Close batch</x-ui.button>
                            </div>
                            @error('closeNote')<p class="rj-error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                </x-ui.card>
            @else
                <x-ui.card><x-ui.empty-state icon="package" title="Choose a batch" message="Pick a batch on the left to receive pieces against it." /></x-ui.card>
            @endif
        </div>
    @endif

    {{-- ===================================================== PAYMENTS --}}
    @if ($tab === 'payments')
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
            <x-ui.card title="Pay a karigar" subtitle="Cash is recorded; metal comes off the raw-metal balance" icon="coins">
                <form wire:submit="pay" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-ui.field label="Karigar" for="kp-v" error="payVendorId">
                            <select id="kp-v" wire:model.live="payVendorId" class="rj-select"><option value="">Choose...</option>@foreach ($karigars as $k)<option value="{{ $k->id }}">{{ $k->name }}</option>@endforeach</select>
                        </x-ui.field>
                        <x-ui.field label="For batch" for="kp-b" optional>
                            <select id="kp-b" wire:model="payBatchId" class="rj-select"><option value="">General</option>@foreach ($open->where('vendor_id', $payVendorId) as $b)<option value="{{ $b->id }}">{{ $b->label }}</option>@endforeach</select>
                        </x-ui.field>
                    </div>
                    <div class="rj-segment"><button type="button" wire:click="$set('payKind', 'cash')" class="{{ $payKind === 'cash' ? 'is-active' : '' }}">Cash</button><button type="button" wire:click="$set('payKind', 'metal')" class="{{ $payKind === 'metal' ? 'is-active' : '' }}">Metal</button></div>
                    @if ($payKind === 'cash')
                        <x-ui.field label="Amount (₹)" for="kp-a" error="payAmount"><input id="kp-a" type="number" step="0.01" min="0" wire:model="payAmount" class="rj-input tabular"></x-ui.field>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <x-ui.field label="Metal" for="kp-m"><select id="kp-m" wire:model.live="payMetal" class="rj-select">@foreach ($metals as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></x-ui.field>
                            <x-ui.field label="Carat" for="kp-p" error="payPurity"><select id="kp-p" wire:model="payPurity" class="rj-select">@foreach ($purities[$payMetal] ?? [] as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach</select></x-ui.field>
                            <x-ui.field label="Weight (g)" for="kp-w" error="payWeight"><input id="kp-w" type="number" step="0.001" min="0" wire:model="payWeight" class="rj-input tabular"></x-ui.field>
                        </div>
                    @endif
                    <x-ui.field label="Note" for="kp-n" optional><input id="kp-n" type="text" wire:model="payNote" maxlength="255" class="rj-input"></x-ui.field>
                    <x-ui.button type="submit" icon="check" target="pay">Record payment</x-ui.button>
                </form>
            </x-ui.card>

            <div class="space-y-6">
                <x-ui.card title="Raw-metal balance" icon="scale">
                    @forelse ($balances as $b)
                        <div class="flex justify-between py-2 border-b border-line-light last:border-0 text-[13.5px]"><span>{{ ucfirst($b->metal) }} {{ $b->purity }}</span><span class="tabular font-semibold">{{ number_format($b->weight, 3) }} g</span></div>
                    @empty <p class="text-[13px] text-ink_text-muted">No raw metal recorded yet.</p> @endforelse
                </x-ui.card>
                <x-ui.card :padding="false" title="Recent payments" icon="history">
                    <x-ui.table :headers="['When', 'Karigar', 'Paid', 'By']">
                        @forelse ($payments as $p)
                            <tr><td class="whitespace-nowrap text-[12.5px] text-ink_text-secondary">{{ $p->created_at->format('j M, g:i a') }}</td><td>{{ $p->vendor->name }}</td>
                                <td class="tabular">{{ $p->kind === 'cash' ? '₹' . number_format($p->amount) : number_format($p->weight, 3) . ' g ' . $p->metal . ' ' . $p->purity }}</td><td>{{ $p->user?->name }}</td></tr>
                        @empty <tr><td colspan="4"><x-ui.empty-state icon="coins" title="No payments yet" compact /></td></tr> @endforelse
                    </x-ui.table>
                </x-ui.card>
            </div>
        </div>
    @endif

    {{-- ===================================================== ALL BATCHES --}}
    @if ($tab === 'batches')
        <x-ui.card :padding="false" title="Batches" icon="layers">
            <x-slot:actions>
                <div class="rj-segment">@foreach (['open' => 'Open', 'closed' => 'Done', 'all' => 'All'] as $v => $l)<button type="button" wire:click="$set('batchFilter', '{{ $v }}')" class="{{ $batchFilter === $v ? 'is-active' : '' }}">{{ $l }}</button>@endforeach</div>
            </x-slot:actions>
            <x-ui.table :headers="['Batch', 'Karigar', 'Description', 'Pieces', 'Pending', 'Status', '']">
                @forelse ($batches as $b)
                    <tr wire:key="ab-{{ $b->id }}">
                        <td class="whitespace-nowrap">{{ $b->label }}</td><td>{{ $b->vendor->name }}</td>
                        <td class="text-ink_text-secondary max-w-[260px] truncate">{{ $b->description ?: $b->purpose_label }}</td>
                        <td class="tabular">{{ $b->pieces_received }} / {{ $b->pieces_expected }}</td>
                        <td class="tabular {{ $b->pieces_pending ? 'text-warning font-semibold' : '' }}">{{ $b->pieces_pending }}</td>
                        <td><x-ui.badge size="sm" :tone="['dispatched' => 'warning', 'partially_returned' => 'info', 'returned' => 'success', 'closed' => 'neutral'][$b->status]">{{ ucfirst(str_replace('_', ' ', $b->status)) }}</x-ui.badge></td>
                        <td class="text-right whitespace-nowrap">
                            @if ($b->is_open)<x-ui.button variant="secondary" size="xs" wire:click="selectBatch({{ $b->id }})">Receive</x-ui.button>@endif
                            <x-ui.button variant="ghost" size="xs" icon="printer" :href="route('movements.karigar.print', $b)" target="_blank">Report</x-ui.button>
                        </td>
                    </tr>
                @empty <tr><td colspan="7"><x-ui.empty-state icon="layers" title="No batches" compact /></td></tr> @endforelse
            </x-ui.table>
        </x-ui.card>
    @endif

    {{-- ===================================================== REPAIRS --}}
    @if ($tab === 'repairs')
        <div class="space-y-10">
            <section><h2 class="font-display text-[22px] font-semibold mb-3">Send out</h2><livewire:movement.karigar-dispatch /></section>
            <section><h2 class="font-display text-[22px] font-semibold mb-3">Receive back</h2><livewire:movement.karigar-return /></section>
        </div>
    @endif
</div>
