@props(['steps', 'current' => 1, 'goTo' => 'goToStep', 'reach' => null])
{{-- Reusable guided-form stepper (8 Oct change list, 1.1). `steps` is [number => label]; `current` the step on screen;
     `reach` the furthest step that can be opened (default: the next one). Clicking calls the Livewire method named by
     `goTo` with the step number, so any earlier step can be reopened and edited. --}}
@php
    $reach ??= $current + 1;
    $total = count($steps);
    $pct = $total > 1 ? round((($current - 1) / ($total - 1)) * 100) : 100;
@endphp
<div {{ $attributes->class(['mb-7']) }}>
    <ol class="flex items-center gap-2 sm:gap-3 overflow-x-auto pb-1" aria-label="Progress">
        @foreach ($steps as $n => $name)
            <li class="flex items-center gap-2 sm:gap-3 shrink-0">
                <button type="button" wire:click="{{ $goTo }}({{ $n }})" @disabled($n > $reach)
                    @if ($n == $current) aria-current="step" @endif
                    class="flex items-center gap-2.5 h-10 pl-1.5 pr-4 rounded-full transition-colors
                    {{ $n == $current ? 'bg-ink text-white shadow-raised' : ($n < $current ? 'bg-white text-ink_text-primary ring-1 ring-line hover:ring-gold-soft' : 'bg-surface-muted text-ink_text-muted') }}">
                    <span class="w-7 h-7 rounded-full flex items-center justify-center text-[12px] font-bold
                        {{ $n == $current ? 'gold-sheen text-white' : ($n < $current ? 'bg-success-bg text-success' : 'bg-white text-ink_text-muted') }}">
                        @if ($n < $current) <x-ui.icon name="check" :size="13" /> @else {{ $n }} @endif
                    </span>
                    <span class="text-[13px] font-semibold whitespace-nowrap">{{ $name }}</span>
                </button>
                @unless ($loop->last)
                    <span class="w-6 sm:w-10 h-px {{ $n < $current ? 'bg-gold' : 'bg-line' }}"></span>
                @endunless
            </li>
        @endforeach
    </ol>
    <div class="mt-3 h-1 rounded-full bg-surface-muted overflow-hidden" role="progressbar" aria-valuemin="1" aria-valuemax="{{ $total }}" aria-valuenow="{{ $current }}">
        <div class="h-full gold-sheen transition-[width] duration-300" style="width: {{ $pct }}%"></div>
    </div>
</div>
