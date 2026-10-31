@php
// Overrides Livewire's default pagination view for every paginated component in the app.
// All page changes go over AJAX (gotoPage / previousPage / nextPage), never a full reload.
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}
$scrollJs = ($scrollTo !== false)
    ? "(\$el.closest('[data-dt]') || \$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView({ behavior: 'smooth', block: 'start' })"
    : '';
$pageName = $paginator->getPageName();
$btn = 'press min-w-[34px] h-[34px] px-2 inline-flex items-center justify-center rounded-lg text-[13px] font-semibold tabular transition-colors';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[12.5px] text-ink_text-secondary">
                Showing <span class="font-semibold text-ink_text-primary tabular">{{ $paginator->firstItem() }}</span>
                to <span class="font-semibold text-ink_text-primary tabular">{{ $paginator->lastItem() }}</span>
                of <span class="font-semibold text-ink_text-primary tabular">{{ $paginator->total() }}</span>
            </p>

            <div class="flex flex-wrap items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="{{ $btn }} text-ink_text-muted/60 cursor-not-allowed" aria-disabled="true" aria-label="Previous page">
                        <x-ui.icon name="chevron-left" :size="15" />
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $pageName }}')" x-on:click="{{ $scrollJs }}" wire:loading.attr="disabled"
                        class="{{ $btn }} text-ink_text-secondary hover:bg-surface-muted hover:text-ink_text-primary" aria-label="Previous page">
                        <x-ui.icon name="chevron-left" :size="15" />
                    </button>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="{{ $btn }} text-ink_text-muted cursor-default">…</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <span wire:key="paginator-{{ $pageName }}-page{{ $page }}">
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="{{ $btn }} bg-ink text-gold-light shadow-[0_4px_10px_-4px_rgba(21,21,21,.5)]">{{ $page }}</span>
                                @else
                                    <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" x-on:click="{{ $scrollJs }}"
                                        class="{{ $btn }} text-ink_text-secondary hover:bg-surface-muted hover:text-ink_text-primary" aria-label="Go to page {{ $page }}">
                                        {{ $page }}
                                    </button>
                                @endif
                            </span>
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $pageName }}')" x-on:click="{{ $scrollJs }}" wire:loading.attr="disabled"
                        class="{{ $btn }} text-ink_text-secondary hover:bg-surface-muted hover:text-ink_text-primary" aria-label="Next page">
                        <x-ui.icon name="chevron-right" :size="15" />
                    </button>
                @else
                    <span class="{{ $btn }} text-ink_text-muted/60 cursor-not-allowed" aria-disabled="true" aria-label="Next page">
                        <x-ui.icon name="chevron-right" :size="15" />
                    </span>
                @endif
            </div>
        </nav>
    @endif
</div>
