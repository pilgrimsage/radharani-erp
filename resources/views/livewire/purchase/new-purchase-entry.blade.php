<div>
    <x-ui.page-header title="Raw-material purchase" subtitle="Metal that came in. It adds to the raw-metal balance. No money is recorded here."
        :crumbs="[['label' => 'Purchases', 'href' => route('purchases.list')], ['label' => 'New purchase']]" />

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
        <x-ui.card title="What came in" subtitle="Identified by the date and time you save it" icon="scale">
            <form wire:submit="save" class="space-y-5">
                <div class="space-y-3">
                    @foreach ($lines as $i => $l)
                        <div class="grid grid-cols-2 sm:grid-cols-[130px_120px_130px_1fr_auto] gap-2 items-start" wire:key="pl-{{ $i }}">
                            <select wire:model.live="lines.{{ $i }}.metal" class="rj-select" aria-label="Metal">@foreach ($metals as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                            <select wire:model="lines.{{ $i }}.purity" class="rj-select" aria-label="Carat">@foreach ($purities[$l['metal']] ?? [] as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach</select>
                            <input type="number" step="0.001" min="0" wire:model="lines.{{ $i }}.weight" placeholder="Weight (g)" class="rj-input tabular @error('lines.' . $i . '.weight') is-invalid @enderror" aria-label="Weight">
                            <input type="text" wire:model="lines.{{ $i }}.description" placeholder="Note (optional)" maxlength="100" class="rj-input col-span-2 sm:col-span-1" aria-label="Line note">
                            <x-ui.button type="button" variant="ghost" size="icon-sm" icon="x" wire:click="removeLine({{ $i }})" aria-label="Remove line" />
                        </div>
                        @error('lines.' . $i . '.weight')<p class="rj-error">{{ $message }}</p>@enderror
                    @endforeach
                </div>
                <x-ui.button type="button" variant="secondary" size="sm" icon="plus" wire:click="addLine">Add a line</x-ui.button>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-line-light">
                    <x-ui.field label="Reference bill number" for="np-ref" error="billRef" optional><input id="np-ref" type="text" wire:model="billRef" maxlength="50" class="rj-input"></x-ui.field>
                    <x-ui.field label="For a custom order" for="np-order" optional>
                        <select id="np-order" wire:model="orderId" class="rj-select"><option value="">Not for an order</option>@foreach ($orders as $o)<option value="{{ $o->id }}">#{{ $o->id }} · {{ $o->customer?->name }} · {{ \Illuminate\Support\Str::limit($o->product_description, 36) }}</option>@endforeach</select>
                    </x-ui.field>
                </div>
                <x-ui.field label="Notes" for="np-notes" error="notes" optional><textarea id="np-notes" rows="3" wire:model="notes" class="rj-textarea"></textarea></x-ui.field>
                <x-ui.button type="submit" size="lg" icon="check" target="save" class="w-full">Record purchase</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Raw-metal balance" icon="scale" class="xl:sticky xl:top-24">
            @forelse ($balances as $b)
                <div class="flex justify-between py-2 border-b border-line-light last:border-0 text-[13.5px]"><span>{{ ucfirst($b->metal) }} {{ $b->purity }}</span><span class="tabular font-semibold">{{ number_format($b->weight, 3) }} g</span></div>
            @empty <p class="text-[13px] text-ink_text-muted">Nothing yet.</p> @endforelse
        </x-ui.card>
    </div>
</div>
