<div>
    <x-ui.page-header title="Pending Messages Queue" subtitle="Copy each message and send it manually (WhatsApp/SMS), then mark it sent." />

    <div class="grid grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
        <x-ui.stat-card icon="clock" label="Pending" :value="number_format($stats['pending'])" />
        <x-ui.stat-card icon="check-circle" label="Sent" :value="number_format($stats['sent'])" />
        <x-ui.stat-card icon="check" label="Sent today" :value="number_format($stats['sentToday'])" />
    </div>

    <x-ui.datatable :paginator="$messages">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search message or recipient" class="w-full sm:w-[280px]" />
            <div class="rj-segment">
                @foreach (['pending' => 'Pending', 'sent' => 'Sent', 'all' => 'All'] as $value => $label)
                    <button type="button" wire:click="$set('statusFilter', '{{ $value }}')" class="{{ $statusFilter === $value ? 'is-active' : '' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="rj-segment">
                <button type="button" wire:click="$set('groupFilter', '')" class="{{ $groupFilter === '' ? 'is-active' : '' }}">Everything</button>
                @foreach (array_keys(\App\Support\MessageTemplates::GROUPS) as $g)
                    <button type="button" wire:click="$set('groupFilter', '{{ $g }}')" class="{{ $groupFilter === $g ? 'is-active' : '' }}">{{ $g }}</button>
                @endforeach
            </div>
            @if ($this->hasNonDefaultFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="type" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Type</x-ui.th>
            <x-ui.th>To</x-ui.th>
            <x-ui.th>Phone</x-ui.th>
            <x-ui.th>Message</x-ui.th>
            <x-ui.th>Status</x-ui.th>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Created</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($messages as $m)
            <tr wire:key="msg-{{ $m->id }}" x-data="{ copied: false }">
                <td><x-ui.badge tone="neutral" size="sm">{{ \App\Support\MessageTemplates::groupOf($m->type) }} · {{ \App\Support\MessageTemplates::label($m->type) }}</x-ui.badge></td>
                <td class="text-ink_text-primary">{{ $m->customer->name ?? $m->recipient_name ?? '—' }}</td>
                <td class="text-ink_text-secondary">{{ $m->customer->phone ?? $m->recipient_phone ?? '—' }}</td>
                <td class="text-ink_text-primary max-w-[320px] truncate" title="{{ $m->message }}">{{ $m->message }}</td>
                <td>
                    @if ($m->status === 'sent')
                        <x-ui.badge tone="success" size="sm" dot>Sent</x-ui.badge>
                    @else
                        <x-ui.badge tone="warning" size="sm" dot>Pending</x-ui.badge>
                    @endif
                </td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $m->created_at?->format('d M Y') }}</td>
                <td>
                    <div class="flex items-center justify-end gap-1.5">
                        <x-ui.button type="button" variant="secondary" size="sm" icon="copy"
                            x-on:click="navigator.clipboard.writeText({{ json_encode($m->message) }}); copied = true; setTimeout(() => copied = false, 1500)">
                            <span x-show="!copied">Copy</span>
                            <span x-show="copied" x-cloak>Copied!</span>
                        </x-ui.button>
                        @if ($m->status === 'pending')
                            <x-ui.button type="button" variant="primary" size="sm" icon="check"
                                x-on:click="$dispatch('rj-confirm', { title: 'Mark as sent?', message: 'Confirm only after you have actually sent this message to the customer.', confirm: 'Mark sent', action: () => $wire.markSent({{ $m->id }}) })">
                                Mark sent
                            </x-ui.button>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    @if ($this->hasNonDefaultFilters())
                        <x-ui.empty-state icon="search" title="No messages match these filters" message="Try a different search or clear the filters.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="bell" title="No pending messages" message="Sale confirmations, order-ready alerts, and other customer messages will queue up here." />
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>
</div>
