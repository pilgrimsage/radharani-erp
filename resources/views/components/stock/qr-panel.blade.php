@props(['qr' => null, 'label', 'type'])
{{-- QR sticker panel on detail pages. Expects the Livewire component to expose issueQr(). --}}
<x-ui.card title="QR label" icon="qr-code">
    @if ($qr)
        <div class="flex items-center gap-4">
            <div class="w-[104px] h-[104px] shrink-0 p-1.5 bg-white rounded-xl ring-1 ring-line shadow-card">
                {!! $qr->svg() !!}
            </div>
            <div class="min-w-0">
                <div class="rj-code text-[14px] text-ink_text-primary">{{ $label }}</div>
                <div class="text-[12px] text-ink_text-muted mt-0.5">Sticker code <span class="rj-code text-[11.5px] text-ink_text-secondary">{{ $qr->code }}</span></div>
                <div class="text-[12px] text-ink_text-muted">Issued {{ $qr->created_at?->format('d M Y') }}</div>
            </div>
        </div>
    @else
        <div class="flex items-center gap-4">
            <div class="w-[104px] h-[104px] shrink-0 rounded-xl border-2 border-dashed border-line flex items-center justify-center text-ink_text-muted">
                <x-ui.icon name="qr-code" :size="28" />
            </div>
            <div>
                <p class="text-[13px] text-ink_text-secondary">No sticker yet. Scanning one later opens this {{ $type }} directly.</p>
                <x-ui.button size="sm" icon="plus" class="mt-3" wire:click="issueQr">Issue QR label</x-ui.button>
            </div>
        </div>
    @endif
</x-ui.card>
