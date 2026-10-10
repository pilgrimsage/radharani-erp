<div>
    <x-ui.page-header title="Photo / Custom Purpose" subtitle="Any other short trip out of the shop: photos for the website, an appraisal, an exhibition, a customer taking a piece to decide."
        :crumbs="[['label' => 'Movements'], ['label' => 'Photo / Custom Purpose']]">
        <x-slot:actions>
            <div class="rj-segment">
                <button type="button" wire:click="setDirection('out')" class="{{ $direction === 'out' ? 'is-active' : '' }}"><x-ui.icon name="arrow-up" :size="14" /> Send out</button>
                <button type="button" wire:click="setDirection('in')" class="{{ $direction === 'in' ? 'is-active' : '' }}">
                    <x-ui.icon name="arrow-down" :size="14" /> Record return
                    @if ($outCount)<span class="min-w-[20px] h-5 px-1.5 rounded-full bg-gold-tint text-gold-dark text-[11px] font-bold tabular inline-flex items-center justify-center">{{ $outCount }}</span>@endif
                </button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($direction === 'out')
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_400px] gap-6 items-start">
            <x-ui.card :padding="false" title="Send pieces out" subtitle="They are marked as out until you record them back." icon="camera">
                <form wire:submit="submitOut">
                    <div class="p-5 sm:p-6 space-y-6">
                        <x-ui.field label="What for" error="purpose">
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach (\App\Livewire\Movement\CustomPurposeMove::PURPOSES as $p => $pair)
                                    <label @class([
                                        'relative flex items-center gap-2.5 h-11 px-3 rounded-control border cursor-pointer transition-colors',
                                        'border-gold bg-gold-tint shadow-focus' => $purpose === $p,
                                        'border-line hover:border-line-strong' => $purpose !== $p,
                                    ])>
                                        <input type="radio" wire:model.live="purpose" value="{{ $p }}" class="sr-only">
                                        <x-ui.icon :name="['Photography' => 'camera', 'Website shoot' => 'image', 'Appraisal' => 'scale', 'Exhibition' => 'star', 'Customer approval' => 'user', 'Other' => 'more-horizontal'][$p]" :size="15"
                                            class="{{ $purpose === $p ? 'text-gold-dark' : 'text-ink_text-muted' }}" />
                                        <span class="text-[13px] font-semibold {{ $purpose === $p ? 'text-ink_text-primary' : 'text-ink_text-secondary' }}">{{ $p }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </x-ui.field>
                        @if ($purpose === 'Other')
                            <x-ui.field label="Reason" for="cp-other" error="otherPurpose">
                                <input id="cp-other" type="text" wire:model="otherPurpose" maxlength="50" class="rj-input @error('otherPurpose') is-invalid @enderror" placeholder="e.g. Valuation for insurance">
                            </x-ui.field>
                        @endif

                        <x-movement.item-picker :items="$basketItems" :results="$pickResults" label="Pieces going out" />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-6 border-t border-line-light">
                            <x-ui.field label="With whom" for="cp-who" error="counterparty" optional hint="Photographer, valuer or customer name">
                                <input id="cp-who" type="text" wire:model="counterparty" maxlength="100" class="rj-input">
                            </x-ui.field>
                            <x-ui.field label="Expected back" for="cp-due" error="expectedReturn">
                                <input id="cp-due" type="date" wire:model.live="expectedReturn" class="rj-input tabular">
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach ([0 => 'Today', 1 => 'Tomorrow', 3 => '3 days', 7 => '7 days'] as $d => $txt)
                                        @php $val = today()->addDays($d)->toDateString(); @endphp
                                        <button type="button" wire:click="$set('expectedReturn', '{{ $val }}')"
                                            class="h-7 px-2.5 rounded-md text-[12px] font-semibold ring-1 ring-inset transition-colors
                                            {{ $expectedReturn === $val ? 'bg-ink text-gold-light ring-ink' : 'bg-white text-ink_text-secondary ring-line hover:ring-line-strong' }}">{{ $txt }}</button>
                                    @endforeach
                                </div>
                            </x-ui.field>
                        </div>

                        <x-movement.photo-upload :photo="$photo" required label="Photo as it left" />

                        <x-movement.done-by />

                        <x-ui.field label="Note" for="cp-note" error="note" optional>
                            <input id="cp-note" type="text" wire:model="note" maxlength="255" class="rj-input">
                        </x-ui.field>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 bg-surface-sunken border-t border-line-light rounded-b-card">
                        <p class="text-[12.5px] text-ink_text-secondary">
                            <span class="font-semibold text-ink_text-primary tabular">{{ $basketItems->count() }}</span> {{ \Illuminate\Support\Str::plural('piece', $basketItems->count()) }}
                            · recorded as {{ \App\Livewire\Movement\CustomPurposeMove::PURPOSES[$purpose] === 'photo' ? 'a photography trip' : 'a custom trip' }}
                        </p>
                        <x-ui.button type="submit" size="lg" icon="arrow-up" target="submitOut">Confirm send out</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card :padding="false" title="Out right now" :subtitle="$out->count() . ' ' . \Illuminate\Support\Str::plural('piece', $out->count()) . ($overdue ? ', ' . $overdue . ' overdue' : '')" icon="clock" class="xl:sticky xl:top-24">
                <x-slot:actions>
                    @if ($out->isNotEmpty())
                        <x-ui.button variant="ghost" size="sm" wire:click="setDirection('in')">Record return</x-ui.button>
                    @endif
                </x-slot:actions>
                <ul class="divide-y divide-line-light max-h-[640px] overflow-y-auto">
                    @forelse ($out as $m)
                        <li class="flex items-start gap-3 px-5 py-3.5">
                            <x-movement.metal-dot :metal="$m->item?->metal" class="mt-1.5" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="rj-code text-ink_text-primary">{{ $m->item?->label }}</span>
                                    <x-movement.due :date="$m->expected_return" />
                                </div>
                                <div class="text-[12px] text-ink_text-muted truncate">{{ $m->purpose_label }}{{ $m->counterparty ? ' · with ' . $m->counterparty : '' }}</div>
                            </div>
                        </li>
                    @empty
                        <li><x-ui.empty-state icon="check-circle" title="Nothing is out" message="Pieces you send out show up here until they come back." compact /></li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    @else
        @php $openIds = $out->pluck('id')->map(fn ($id) => (string) $id)->all(); @endphp
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_380px] gap-6 items-start">
            <x-ui.datatable>
                <x-slot:toolbar>
                    <x-ui.search-input scan scan-continuous scan-title="Scan pieces that came back" wire:model.live.debounce.300ms="search" placeholder="Code, reason or person" class="w-full sm:w-[280px]" />
                    <span class="ml-auto text-[12.5px] text-ink_text-muted">{{ $out->count() }} out</span>
                </x-slot:toolbar>
                <x-slot:head>
                    <th class="w-10 !pr-0">
                        <input type="checkbox" class="rj-checkbox" aria-label="Select all"
                            @checked(count($openIds) && ! array_diff($openIds, $returning))
                            x-on:change="$wire.set('returning', $event.target.checked ? @js($openIds) : [])">
                    </th>
                    <th>Piece</th><th>For</th><th>With</th><th>Sent</th><th>Due</th>
                </x-slot:head>
                @forelse ($out as $m)
                    <tr wire:key="ret-{{ $m->id }}" @class(['is-selected' => in_array((string) $m->id, $returning, true)])>
                        <td class="!pr-0"><input type="checkbox" class="rj-checkbox" value="{{ $m->id }}" wire:model.live="returning" aria-label="Returned {{ $m->item?->label }}"></td>
                        <td>
                            <div class="flex items-center gap-2">
                                <x-movement.metal-dot :metal="$m->item?->metal" />
                                <span class="rj-code">{{ $m->item?->label }}</span>
                            </div>
                            <div class="text-[12.5px] text-ink_text-muted">{{ $m->item?->category }} · <span class="tabular">{{ number_format((float) $m->weight_at_dispatch, 3) }} g</span></div>
                        </td>
                        <td>
                            <x-ui.badge :tone="$m->movement_type === 'photo_out' ? 'gold' : 'info'" size="sm">{{ $m->purpose_label }}</x-ui.badge>
                            @if ($m->photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($m->photo_path))
                                <a href="{{ asset('storage/' . $m->photo_path) }}" target="_blank" class="ml-1 inline-flex align-middle text-ink_text-muted hover:text-gold-dark" title="Photo taken when it left"><x-ui.icon name="image" :size="14" /></a>
                            @elseif ($m->photo_path)
                                <span class="ml-1 inline-flex align-middle text-ink_text-muted" title="Photo expired and was removed after 90 days"><x-ui.icon name="image" :size="14" /></span>
                            @endif
                        </td>
                        <td class="text-[13px]">{{ $m->counterparty ?: '-' }}</td>
                        <td class="whitespace-nowrap text-[13px]">{{ $m->created_at->format('j M, g:i a') }}<div class="text-[12px] text-ink_text-muted">{{ $m->user?->name }}</div></td>
                        <td><x-movement.due :date="$m->expected_return" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6">
                        <x-ui.empty-state icon="check-circle" :title="$search ? 'Nothing matches' : 'Nothing is out'" :message="$search ? 'Try another search.' : 'Every piece sent out has come back.'" compact />
                    </td></tr>
                @endforelse
            </x-ui.datatable>

            <x-ui.card title="Back in the shop" icon="arrow-down" class="xl:sticky xl:top-24">
                <form wire:submit="submitReturn" class="space-y-5">
                    <div class="flex items-baseline justify-between">
                        <span class="text-[13px] text-ink_text-secondary">Ticked</span>
                        <span class="font-display text-[34px] leading-none font-semibold tabular">{{ count($returning) }}</span>
                    </div>
                    @error('returning') <p class="rj-error -mt-3"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p> @enderror

                    <x-movement.photo-upload :photo="$photo" required label="Photo as it came back" hint="Proof of condition. Compressed when saved." />

                    <x-movement.done-by />

                    <x-ui.field label="Note" for="cp-rnote" error="note" optional>
                        <input id="cp-rnote" type="text" wire:model="note" maxlength="255" class="rj-input">
                    </x-ui.field>

                    <x-ui.button type="submit" size="lg" class="w-full" icon="check" target="submitReturn">Confirm return to stock</x-ui.button>
                    <p class="text-[12px] text-ink_text-muted text-center -mt-2">Pieces go straight back to In stock.</p>
                </form>
            </x-ui.card>
        </div>
    @endif
</div>
