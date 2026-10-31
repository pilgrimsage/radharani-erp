<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Radharani Jewellery ERP' }}</title>
@include('components.layouts.partials.favicon')
<script>
    // Apply the saved sidebar state before first paint so the layout never jumps.
    try { if (localStorage.getItem('rj.sidebar') === 'collapsed') document.documentElement.classList.add('sb-collapsed'); } catch (e) {}
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
</head>
<body class="font-sans antialiased" x-data="appShell()" x-on:keydown.window="shortcut($event)">

@include('components.layouts.partials.sidebar')

{{-- Mobile drawer backdrop --}}
<div x-show="mobileNav" x-cloak x-transition.opacity.duration.200ms x-on:click="mobileNav = false"
     class="fixed inset-0 z-[55] bg-[#1A150C]/50 backdrop-blur-[2px] lg:hidden"></div>

<div class="app-main min-h-[100dvh] flex flex-col">
    @include('components.layouts.partials.topbar')

    <main class="flex-1 w-full max-w-[1520px] mx-auto px-4 sm:px-6 lg:px-9 py-7 lg:py-8">
        {{ $slot }}
    </main>
</div>

{{-- Toasts: $this->dispatch('toast', message: '...', type: 'success|error|info|warning') from any component --}}
<div class="fixed bottom-5 right-5 z-toast flex flex-col items-end gap-2.5 pointer-events-none w-[calc(100%-2.5rem)] sm:w-auto"
     x-data="toasts()" x-on:toast.window="push($event.detail)"
     @if (session('status') || session('toast')) x-init="push({ message: @js(session('toast') ?? session('status')), type: 'success' })" @endif>
    <template x-for="t in items" :key="t.id">
        <div x-show="t.visible"
             x-transition:enter="transition ease-silk duration-300" x-transition:enter-start="opacity-0 translate-y-2 scale-[.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0 translate-x-4"
             class="pointer-events-auto w-full sm:w-[360px] bg-ink text-white rounded-xl shadow-modal ring-1 ring-white/5 overflow-hidden" role="status">
            <div class="flex items-start gap-3 px-4 py-3.5">
                <div class="mt-0.5 w-6 h-6 shrink-0 rounded-full flex items-center justify-center"
                     :class="{ 'bg-success/20 text-[#6FD3A5]': t.type === 'success', 'bg-danger/25 text-[#F2A0A0]': t.type === 'error', 'bg-gold/20 text-gold-light': t.type === 'warning' || t.type === 'info' }">
                    <template x-if="t.type === 'success'"><x-ui.icon name="check" :size="13" /></template>
                    <template x-if="t.type === 'error'"><x-ui.icon name="x" :size="13" /></template>
                    <template x-if="t.type === 'warning' || t.type === 'info'"><x-ui.icon name="info" :size="13" /></template>
                </div>
                <p class="flex-1 text-[13px] leading-snug text-[#EDE9E0] pt-[3px]" x-text="t.message"></p>
                <button type="button" x-on:click="dismiss(t.id)" class="text-ink-dim hover:text-white -mr-1" aria-label="Dismiss">
                    <x-ui.icon name="x" :size="15" />
                </button>
            </div>
            <div class="h-[2px] bg-white/5"><div class="h-full gold-sheen origin-left" :style="`animation: rj-toast-timer ${t.timeout}ms linear forwards`"></div></div>
        </div>
    </template>
</div>

{{-- Confirm dialog: $dispatch('rj-confirm', { title, message, confirm, tone: 'danger', action: () => $wire.someMethod(1) }) --}}
<div x-data="confirmDialog()" x-on:rj-confirm.window="ask($event.detail)" x-show="open" x-cloak
     x-on:keydown.escape.window="open = false" class="fixed inset-0 z-modal" role="alertdialog" aria-modal="true">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-[#1A150C]/45 backdrop-blur-[3px]"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4" x-on:click.self="open = false">
        <div x-show="open" x-trap.noscroll="open"
             x-transition:enter="transition ease-silk duration-300" x-transition:enter-start="opacity-0 translate-y-2 scale-[.97]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0 scale-[.97]"
             class="w-full max-w-[420px] bg-white rounded-[18px] shadow-modal overflow-hidden">
            <div class="p-6">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-4"
                     :class="tone === 'danger' ? 'bg-danger-bg text-danger' : 'bg-gold-tint text-gold-dark'">
                    <x-ui.icon name="alert-triangle" :size="19" />
                </div>
                <h2 class="font-display text-[23px] leading-tight font-semibold text-ink_text-primary" x-text="title"></h2>
                <p class="text-[13.5px] text-ink_text-secondary mt-1.5 leading-relaxed" x-text="message"></p>
            </div>
            <div class="flex justify-end gap-2.5 px-6 py-4 bg-surface-sunken border-t border-line-light">
                <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                <button type="button" x-on:click="run()" x-ref="confirmBtn"
                    class="press h-10 px-4 rounded-control text-[13px] font-semibold text-white border"
                    :class="tone === 'danger' ? 'bg-danger border-danger hover:bg-[#B64040]' : 'gold-sheen border-gold-dark/30 shadow-gold'"
                    x-text="confirmText"></button>
            </div>
        </div>
    </div>
</div>

@include('components.layouts.partials.scanner')

<style>@keyframes rj-toast-timer { from { transform: scaleX(1) } to { transform: scaleX(0) } }</style>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('appShell', () => ({
        mobileNav: false,
        collapsed: document.documentElement.classList.contains('sb-collapsed'),
        toggleSidebar() {
            if (window.innerWidth < 1024) { this.mobileNav = !this.mobileNav; return; }
            this.collapsed = !this.collapsed;
            document.documentElement.classList.toggle('sb-collapsed', this.collapsed);
            try { localStorage.setItem('rj.sidebar', this.collapsed ? 'collapsed' : 'expanded'); } catch (e) {}
        },
        shortcut(e) {
            const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable;
            if ((e.key === '/' && !typing) || ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k')) {
                const el = document.getElementById('global-search');
                if (el) { e.preventDefault(); el.focus(); el.select(); }
            }
            if ((e.metaKey || e.ctrlKey) && e.key === '\\') { e.preventDefault(); this.toggleSidebar(); }
        },
    }));

    // Rail tooltip / flyout positioning for the collapsed sidebar.
    window.railTip = () => ({
        hovering: false,
        top: 0,
        railMode() { return document.documentElement.classList.contains('sb-collapsed') && window.innerWidth >= 1024; },
        show(el) { if (!this.railMode()) return; this.top = Math.min(el.getBoundingClientRect().top, window.innerHeight - 320); this.hovering = true; },
        hide() { this.hovering = false; },
    });

    Alpine.data('toasts', () => ({
        items: [],
        push(detail) {
            const d = Array.isArray(detail) ? detail[0] : detail;
            if (!d || !d.message) return;
            const id = Date.now() + Math.random();
            const timeout = d.type === 'error' ? 6000 : 3800;
            this.items.push({ id, message: d.message, type: d.type || 'success', visible: true, timeout });
            setTimeout(() => this.dismiss(id), timeout);
        },
        dismiss(id) {
            const t = this.items.find(i => i.id === id);
            if (t) t.visible = false;
            setTimeout(() => { this.items = this.items.filter(i => i.id !== id); }, 250);
        },
    }));

    Alpine.data('confirmDialog', () => ({
        open: false, title: '', message: '', confirmText: 'Confirm', tone: 'gold', action: null,
        ask(d) {
            this.title = d.title || 'Are you sure?';
            this.message = d.message || '';
            this.confirmText = d.confirm || 'Confirm';
            this.tone = d.tone || 'gold';
            this.action = d.action || null;
            this.open = true;
            this.$nextTick(() => this.$refs.confirmBtn && this.$refs.confirmBtn.focus());
        },
        run() {
            this.open = false;
            if (typeof this.action === 'function') this.action();
        },
    }));
});
</script>
@livewireScripts
</body>
</html>
