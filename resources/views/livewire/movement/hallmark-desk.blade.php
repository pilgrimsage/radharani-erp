<div>
    <x-ui.page-header title="Hallmarking" subtitle="Send a batch to the centre, then receive it back in as many parts as it takes."
        :crumbs="[['label' => 'Movements'], ['label' => 'Hallmarking']]">
        <x-slot:actions>
            @if (\Illuminate\Support\Facades\Route::has('ledgers.index'))
                <x-ui.button variant="secondary" icon="book" :href="route('ledgers.index', ['kind' => 'hallmarker'])">Hallmarker ledgers</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rj-segment mb-6" role="tablist">
        @foreach (['dispatch' => 'Dispatch', 'receive' => 'Receive (' . $open->count() . ' open)', 'batches' => 'All batches'] as $k => $label)
            <button type="button" role="tab" wire:click="setTab('{{ $k }}')" class="{{ $tab === $k ? 'is-active' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ================================================ DISPATCH --}}
    @if ($tab === 'dispatch')
        <x-ui.card title="Send a batch" subtitle="Identified by the date and time you save it" icon="shield-check">
            <form wire:submit="dispatchBatch" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.field label="Hallmarking centre" for="hk-centre" error="centreId">
                        <select id="hk-centre" wire:model="centreId" class="rj-select @error('centreId') is-invalid @enderror">
                            <option value="">Choose...</option>
                            @foreach ($centres as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                        @if ($centres->isEmpty())<p class="rj-help">No centres yet. Add one under Administration, Karigars & Centres.</p>@endif
                    </x-ui.field>
                    <x-ui.field label="Expected back" for="hk-due" error="expectedReturn"><input id="hk-due" type="date" min="{{ today()->toDateString() }}" wire:model="expectedReturn" class="rj-input tabular"></x-ui.field>
                </div>

                <div>
                    <div class="rj-label">Where it comes from</div>
                    <div class="rj-segment"><button type="button" wire:click="$set('source', 'stock')" class="{{ $source === 'stock' ? 'is-active' : '' }}">From stock</button><button type="button" wire:click="$set('source', 'order')" class="{{ $source === 'order' ? 'is-active' : '' }}">For an order</button></div>
                    @if ($source === 'order')
                        <x-ui.field label="Order" for="hk-order" error="orderId" class="mt-3">
                            <select id="hk-order" wire:model="orderId" class="rj-select"><option value="">Choose...</option>
                                @foreach ($orders as $o)<option value="{{ $o->id }}">#{{ $o->id }} · {{ $o->customer?->name }} · {{ \Illuminate\Support\Str::limit($o->product_description, 40) }}</option>@endforeach</select>
                        </x-ui.field>
                    @endif
                </div>

                <x-ui.field label="Description" for="hk-desc" error="description" hint="One line for the whole batch."><input id="hk-desc" type="text" wire:model="description" maxlength="150" class="rj-input @error('description') is-invalid @enderror"></x-ui.field>

                <section class="rounded-xl ring-1 ring-inset ring-line-light p-4 space-y-4">
                    <h3 class="text-[13px] font-bold">Untagged pieces, by count</h3>
                    <p class="rj-help -mt-2">They go out untagged. Do not tag pieces that will not get a HUID.</p>
                    @if ($fromKarigar->isNotEmpty())
                        <div>
                            <div class="text-[12.5px] font-semibold text-ink_text-secondary mb-2">Waiting from karigars</div>
                            <div class="space-y-1.5">
                                @foreach ($fromKarigar as $r)
                                    <label class="flex items-center gap-2.5 text-[13px]" wire:key="kr-{{ $r->id }}"><input type="checkbox" value="{{ $r->id }}" wire:model="karigarReceiptIds" class="rj-checkbox">
                                        {{ $r->pieces }} {{ \Illuminate\Support\Str::plural('piece', $r->pieces) }} · {{ number_format($r->weight_received, 3) }} g · from {{ $r->batch->vendor->name }}, {{ $r->created_at->format('j M, g:i a') }}</label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-ui.field label="More pieces" for="hk-pc" error="piecesCounted" optional><input id="hk-pc" type="number" min="0" wire:model="piecesCounted" class="rj-input tabular"></x-ui.field>
                        <x-ui.field label="Their total weight (g)" for="hk-wc" error="weightCounted" optional><input id="hk-wc" type="number" step="0.001" min="0" wire:model="weightCounted" class="rj-input tabular"></x-ui.field>
                        <x-ui.field label="To receive a HUID" for="hk-he" error="huidExpected" optional hint="Leave empty if all of them."><input id="hk-he" type="number" min="0" wire:model="huidExpected" class="rj-input tabular"></x-ui.field>
                    </div>
                </section>

                <section class="rounded-xl ring-1 ring-inset ring-line-light p-4 space-y-3">
                    <h3 class="text-[13px] font-bold">Tagged pieces from stock</h3>
                    <x-movement.item-picker :items="$basketItems" :results="$pickResults" label="Pieces" />
                </section>

                <x-ui.field label="Note" for="hk-note" optional><input id="hk-note" type="text" wire:model="note" maxlength="255" class="rj-input" placeholder="e.g. challan number"></x-ui.field>
                <x-movement.photo-upload :photo="$photo" required label="Photo of what is going out" />
                <x-movement.done-by />
                <x-ui.button type="submit" size="lg" icon="truck" target="dispatchBatch" class="w-full">Confirm dispatch</x-ui.button>
            </form>
        </x-ui.card>
    @endif

    {{-- ================================================ RECEIVE --}}
    @if ($tab === 'receive')
        <div class="grid grid-cols-1 xl:grid-cols-[360px_minmax(0,1fr)] gap-6 items-start">
            <x-ui.card :padding="false" title="Open batches" :subtitle="$open->count() . ' with pieces still out'" icon="clock">
                <ul class="divide-y divide-line-light max-h-[640px] overflow-y-auto">
                    @forelse ($open as $b)
                        <li wire:key="hb-{{ $b->id }}"><button type="button" wire:click="selectBatch({{ $b->id }})" class="w-full text-left px-5 py-3.5 hover:bg-surface-sunken {{ $batchId === $b->id ? 'bg-gold-tint/50' : '' }}">
                            <div class="flex items-baseline justify-between gap-2"><span class="font-semibold text-ink_text-primary">{{ $b->centre->name }}</span><span class="text-[12px] tabular text-ink_text-muted">{{ $b->expected_return?->format('j M') ?? 'no date' }}</span></div>
                            <div class="text-[12.5px] text-ink_text-secondary truncate">{{ $b->label }} · {{ $b->description }}</div>
                            <div class="text-[12.5px] mt-1"><span class="font-semibold text-warning">{{ $b->pieces_pending }} pending</span> <span class="text-ink_text-muted">of {{ $b->pieces_out }}</span></div>
                        </button></li>
                    @empty <li><x-ui.empty-state icon="check-circle" title="Nothing out" message="Every batch is back or closed." compact /></li> @endforelse
                </ul>
            </x-ui.card>

            @if ($batch)
                <x-ui.card :title="'Receive from ' . $batch->centre->name" :subtitle="'Batch ' . $batch->label . ' · ' . $batch->pieces_back . ' of ' . $batch->pieces_out . ' back, ' . $batch->pieces_pending . ' still out'" icon="shield-check">
                    <form wire:submit="receive" class="space-y-6">
                        @if ($batch->lines->where('returned', false)->isNotEmpty())
                            <section>
                                <h3 class="text-[13px] font-bold mb-2">Tagged pieces in this batch</h3>
                                <div class="space-y-2">
                                    @foreach ($batch->lines->where('returned', false) as $l)
                                        <div class="grid grid-cols-[auto_1fr_120px_110px] gap-3 items-center" wire:key="hl-{{ $l->id }}">
                                            <input type="checkbox" wire:model="lineBack.{{ $l->id }}" class="rj-checkbox" aria-label="Back: {{ $l->item->label }}">
                                            <span class="rj-code truncate">{{ $l->item->label }} <span class="text-ink_text-muted font-sans text-[12px]">{{ $l->item->category }}</span></span>
                                            <input type="text" wire:model="lineHuid.{{ $l->id }}" placeholder="HUID" maxlength="6" class="rj-input rj-code uppercase @error('lineHuid.' . $l->id) is-invalid @enderror">
                                            <input type="number" step="0.001" wire:model="lineWeight.{{ $l->id }}" class="rj-input tabular @error('lineWeight.' . $l->id) is-invalid @enderror" aria-label="Weight">
                                        </div>
                                    @endforeach
                                </div>
                                <p class="rj-help">Tick the ones that came back. Check the HUID and weight; an edited weight is logged.</p>
                            </section>
                        @endif

                        <section class="space-y-4">
                            <h3 class="text-[13px] font-bold">New pieces from the counted batch</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                                <x-ui.field label="Metal" for="hr-metal"><select id="hr-metal" wire:model.live="metal" class="rj-select">@foreach ($metals as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></x-ui.field>
                                <x-ui.field label="Subcategory" for="hr-cat" error="categoryId"><select id="hr-cat" wire:model="categoryId" class="rj-select"><option value="">Choose...</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></x-ui.field>
                                <x-ui.field label="Carat" for="hr-pur" error="purity"><select id="hr-pur" wire:model="purity" class="rj-select">@foreach ($purities[$metal] ?? [] as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach</select></x-ui.field>
                                <x-ui.field label="Add to packet" for="hr-pkt" optional><select id="hr-pkt" wire:model="packetId" class="rj-select"><option value="">Decide later</option>@foreach ($packets as $p)<option value="{{ $p->id }}">{{ $p->code }}{{ $p->box ? ' (' . $p->box->code . ')' : '' }}</option>@endforeach</select></x-ui.field>
                            </div>
                            <x-ui.field label="With a HUID" for="hr-with" error="withHuid" hint="One piece per line: HUID, weight, then an optional description. Paste several at once.">
                                <textarea id="hr-with" rows="4" wire:model="withHuid" class="rj-textarea rj-code @error('withHuid') is-invalid @enderror" placeholder="TIEAPZ 1.765 chain&#10;JPFMZG 3.590"></textarea>
                            </x-ui.field>
                            <x-ui.field label="Without a HUID (under 2 g)" for="hr-without" error="withoutHuid" hint="One piece per line: weight, then an optional description. Each gets an internal code.">
                                <textarea id="hr-without" rows="3" wire:model="withoutHuid" class="rj-textarea rj-code @error('withoutHuid') is-invalid @enderror" placeholder="0.850 nose pin"></textarea>
                            </x-ui.field>
                        </section>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-ui.field label="Tagged by" for="hr-tag" error="taggedBy"><input id="hr-tag" type="text" wire:model="taggedBy" maxlength="100" class="rj-input @error('taggedBy') is-invalid @enderror" placeholder="Name at the centre"></x-ui.field>
                            <x-ui.field label="Weight loss (g)" for="hr-loss" optional hint="Typed in, never worked out."><input id="hr-loss" type="number" step="0.001" min="0" wire:model="lossWeight" class="rj-input tabular"></x-ui.field>
                        </div>
                        <x-ui.field label="Note" for="hr-note" optional><input id="hr-note" type="text" wire:model="receiveNote" maxlength="255" class="rj-input"></x-ui.field>
                        <x-movement.photo-upload :photo="$photo" required label="Photo of what came back" />
                        <x-movement.done-by />
                        <x-ui.button type="submit" size="lg" icon="check" target="receive" class="w-full">Receive</x-ui.button>
                    </form>

                    @if ($canClose)
                        <div class="mt-6 pt-5 border-t border-line-light">
                            <h3 class="text-[13px] font-bold mb-2">Close the {{ $batch->pieces_pending }} still out</h3>
                            <div class="flex gap-2"><input type="text" wire:model="closeNote" maxlength="255" placeholder="Why the rest will not come back" class="rj-input flex-1" aria-label="Close note">
                                <x-ui.button variant="danger-soft" wire:click="closeRemainder" target="closeRemainder">Close batch</x-ui.button></div>
                            @error('closeNote')<p class="rj-error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                </x-ui.card>
            @else
                <x-ui.card><x-ui.empty-state icon="shield-check" title="Choose a batch" message="Pick a batch on the left to receive pieces against it." /></x-ui.card>
            @endif
        </div>
    @endif

    {{-- ================================================ BATCHES --}}
    @if ($tab === 'batches')
        <x-ui.card :padding="false" title="Batches" icon="layers">
            <x-slot:actions><div class="rj-segment">@foreach (['open' => 'Open', 'closed' => 'Done', 'all' => 'All'] as $v => $l)<button type="button" wire:click="$set('batchFilter', '{{ $v }}')" class="{{ $batchFilter === $v ? 'is-active' : '' }}">{{ $l }}</button>@endforeach</div></x-slot:actions>
            <x-ui.table :headers="['Batch', 'Centre', 'Description', 'Pieces', 'Still out', 'Status', '']">
                @forelse ($batches as $b)
                    <tr wire:key="hbl-{{ $b->id }}">
                        <td class="whitespace-nowrap">{{ $b->label }}</td><td>{{ $b->centre->name }}</td><td class="text-ink_text-secondary max-w-[260px] truncate">{{ $b->description }}</td>
                        <td class="tabular">{{ $b->pieces_back }} / {{ $b->pieces_out }}</td><td class="tabular {{ $b->pieces_pending ? 'text-warning font-semibold' : '' }}">{{ $b->status === 'closed' ? 0 : $b->pieces_pending }}</td>
                        <td><x-ui.badge size="sm" :tone="['dispatched' => 'warning', 'partially_returned' => 'info', 'returned' => 'success', 'closed' => 'neutral'][$b->status]">{{ ucfirst(str_replace('_', ' ', $b->status)) }}</x-ui.badge></td>
                        <td class="text-right">@if ($b->is_open)<x-ui.button variant="secondary" size="xs" wire:click="selectBatch({{ $b->id }})">Receive</x-ui.button>@endif</td>
                    </tr>
                @empty <tr><td colspan="7"><x-ui.empty-state icon="layers" title="No batches" compact /></td></tr> @endforelse
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
