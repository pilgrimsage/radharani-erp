<div>
    <x-ui.page-header title="Referral" subtitle="Opt-in codes. See how much metal was bought against each one, and award points by the owner's rules."
        :crumbs="[['label' => 'Referral']]" />

    <div class="rj-segment mb-6" role="tablist">
        @foreach (['codes' => 'Codes', 'waiting' => 'Sales waiting for points', 'points' => 'Points', 'rules' => 'Rules'] as $k => $l)
            <button type="button" role="tab" wire:click="setTab('{{ $k }}')" class="{{ $tab === $k ? 'is-active' : '' }}">{{ $l }}</button>
        @endforeach
    </div>

    @if ($tab === 'codes')
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
            <x-ui.card :padding="false" title="Customers with a code" icon="gift">
                <x-slot:actions><x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Name or code" class="w-[200px]" /></x-slot:actions>
                <x-ui.table :headers="['Customer', 'Code', 'People referred', 'Sales', 'Metal bought (g)', '']">
                    @forelse ($codes as $c)
                        @php $st = $stats[$c->id] ?? null; @endphp
                        <tr wire:key="rc-{{ $c->id }}">
                            <td class="font-semibold text-ink_text-primary">{{ $c->name }}<span class="block text-[12px] text-ink_text-muted tabular font-normal">{{ $c->phone }}</span></td>
                            <td class="rj-code">{{ $c->referral_code }}</td>
                            <td class="tabular">{{ $st->customers ?? 0 }}</td>
                            <td class="tabular">{{ $st->sales ?? 0 }}</td>
                            <td class="tabular font-semibold">{{ number_format((float) ($st->grams ?? 0), 3) }}</td>
                            <td class="text-right"><x-ui.button variant="ghost" size="sm" x-on:click="$dispatch('rj-confirm', { title: 'Withdraw this code?', message: 'The customer leaves the programme. Sales already made against the code keep their record.', confirm: 'Withdraw', tone: 'danger', action: () => $wire.withdraw({{ $c->id }}) })">Withdraw</x-ui.button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state icon="gift" title="Nobody has a code yet" message="Customers who want to take part get one here." compact /></td></tr>
                    @endforelse
                </x-ui.table>
            </x-ui.card>

            <x-ui.card title="Give a customer a code" subtitle="Only for customers who ask for one" icon="plus">
                <div class="rj-input-icon"><x-ui.icon name="search" :size="16" /><input type="text" wire:model.live.debounce.250ms="customerSearch" placeholder="Name or phone" class="rj-input" autocomplete="off"></div>
                @if ($customerResults->isNotEmpty())
                    <ul class="mt-2 rounded-xl border border-line-light divide-y divide-line-light overflow-hidden">
                        @foreach ($customerResults as $c)<li><button type="button" wire:click="optIn({{ $c->id }})" class="w-full text-left px-4 py-2.5 hover:bg-surface-sunken flex justify-between"><span class="font-semibold">{{ $c->name }}</span><span class="text-ink_text-muted tabular">{{ $c->phone }}</span></button></li>@endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    @endif

    @if ($tab === 'waiting')
        <x-ui.card :padding="false" title="Verified sales made against a code" subtitle="Points come from the rules. Change the number if you need to, then award." icon="clock">
            <x-ui.table :headers="['Sale', 'Buyer', 'Referred by', 'Metal (g)', 'Suggested', 'Points', '']">
                @forelse ($waiting as $w)
                    <tr wire:key="rw-{{ $w['sale']->id }}">
                        <td><a href="{{ route('sales.invoice', $w['sale']) }}" class="rj-code hover:text-gold-dark">{{ $w['sale']->invoice_number }}</a></td>
                        <td>{{ $w['sale']->customer?->name }}@if ($w['first'])<x-ui.badge size="sm" tone="gold">First sale</x-ui.badge>@endif</td>
                        <td>{{ $w['sale']->referrer?->name }}</td>
                        <td class="tabular">{{ number_format($w['grams'], 3) }}</td>
                        <td class="tabular">{{ $w['suggested'] }}</td>
                        <td><input type="number" min="0" wire:model="waitingPoints.{{ $w['sale']->id }}" placeholder="{{ $w['suggested'] }}" class="rj-input tabular w-[110px] @error('waitingPoints.' . $w['sale']->id) is-invalid @enderror" aria-label="Points"></td>
                        <td class="text-right"><x-ui.button size="sm" icon="check" wire:click="award({{ $w['sale']->id }})" target="award">Award</x-ui.button></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="check-circle" title="Nothing waiting" message="Verified sales made with a referral code show up here until their points are awarded." compact /></td></tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>
    @endif

    @if ($tab === 'points')
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
            <x-ui.card :padding="false" title="Points awarded" icon="history">
                <x-ui.table :headers="['When', 'Customer', 'Points', 'Note', 'By']">
                    @forelse ($ledger as $p)
                        <tr><td class="whitespace-nowrap text-[12.5px] text-ink_text-secondary">{{ $p->created_at->format('j M Y, g:i a') }}</td><td>{{ $p->customer?->name }}<span class="block text-[12px] text-ink_text-muted">Total {{ $totals[$p->customer_id] ?? 0 }}</span></td>
                            <td class="tabular font-semibold {{ $p->points < 0 ? 'text-danger' : 'text-success' }}">{{ $p->points > 0 ? '+' : '' }}{{ $p->points }}</td><td>{{ $p->note }}</td><td>{{ $p->user?->name }}</td></tr>
                    @empty <tr><td colspan="5"><x-ui.empty-state icon="gift" title="No points yet" compact /></td></tr> @endforelse
                </x-ui.table>
            </x-ui.card>
            <x-ui.card title="Add or take off points" icon="plus">
                <form wire:submit="awardManual" class="space-y-3">
                    <x-ui.field label="Customer" for="rp-c" error="awardCustomerId"><select id="rp-c" wire:model="awardCustomerId" class="rj-select"><option value="">Choose...</option>@foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field label="Points" for="rp-p" error="awardPoints" hint="Use a minus to take points off."><input id="rp-p" type="number" wire:model="awardPoints" class="rj-input tabular"></x-ui.field>
                    <x-ui.field label="Note" for="rp-n" optional><input id="rp-n" type="text" wire:model="awardNote" maxlength="255" class="rj-input"></x-ui.field>
                    <x-ui.button type="submit" icon="check" target="awardManual">Record</x-ui.button>
                </form>
            </x-ui.card>
        </div>
    @endif

    @if ($tab === 'rules')
        <x-ui.card title="How points are worked out" subtitle="Set by the owner. They are suggestions on the waiting list until staff award them." icon="scale" class="max-w-[620px]">
            <form wire:submit="saveRules" class="space-y-4">
                <x-ui.field label="Points per gram" for="rr-g" error="pointsPerGram" hint="For each gram of metal the referred customer buys."><input id="rr-g" type="number" step="0.01" min="0" wire:model="pointsPerGram" class="rj-input tabular max-w-[200px]" @disabled(! $canSetRules)></x-ui.field>
                <x-ui.field label="Bonus on their first sale" for="rr-b" error="firstSaleBonus" hint="Flat points on top, once, for the referred customer's first verified sale."><input id="rr-b" type="number" min="0" wire:model="firstSaleBonus" class="rj-input tabular max-w-[200px]" @disabled(! $canSetRules)></x-ui.field>
                @if ($canSetRules)<x-ui.button type="submit" icon="check" target="saveRules">Save rules</x-ui.button>@else<p class="rj-help">Only the owner can change the rules.</p>@endif
            </form>
        </x-ui.card>
    @endif
</div>
