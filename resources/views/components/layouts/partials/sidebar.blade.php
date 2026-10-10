@php
use Illuminate\Support\Facades\Route;

$user = auth()->user();

// Live counts for queues that need someone's attention.
$pendingReview = \App\Models\Stock\Item::where('status', 'pending_review')->count();
$pendingSales = \App\Models\Sales\Sale::where('confirmed_by_accountant', false)->count();
$pendingMessages = \App\Models\Notification\PendingNotification::where('status', 'pending')->count();

// Hidden for now (8 Oct change list, section 18): Accounting, Loyalty, Vendors. Routes are switched off in routes/web.php and routes/admin.php.
// Single source for the whole navigation. 'can' is a permission (or list, any-of) gate.
$nav = [
    ['type' => 'link', 'route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid'],
    ['key' => 'stock', 'label' => 'Stock', 'icon' => 'gem', 'can' => 'stock.manage', 'items' => [
        ['route' => 'stock.items', 'label' => 'Inventory'],
        ['route' => 'stock.summary', 'label' => 'Product View'],
        ['route' => 'stock.audit', 'label' => 'Stock Audit', 'can' => 'stock.audit'],
        ['route' => 'stock.unassigned', 'label' => 'Unassigned Items'],
        ['route' => 'stock.boxes', 'label' => 'Boxes & Packets'],
        ['route' => 'stock.categories', 'label' => 'Categories', 'can' => 'category.manage'],
        ['route' => 'stock.assign', 'label' => 'Change Item location'],
        ['route' => 'stock.qr-codes', 'label' => 'QR Codes'],
        ['route' => 'stock.import', 'label' => 'Bulk Import'],
        ['route' => 'stock.huid', 'label' => 'HUID Export / Update'],
        ['route' => 'stock.configurator', 'label' => 'Configurator'],
    ]],
    ['key' => 'movements', 'label' => 'Movements', 'icon' => 'repeat', 'items' => [
        ['route' => 'movements.vault-counter', 'label' => 'Vault ↔ Counter'],
        ['route' => 'movements.karigar', 'label' => 'Karigar'],
        ['route' => 'movements.hallmark', 'label' => 'Hallmarking'],
        ['route' => 'movements.custom-purpose', 'label' => 'Photo / Custom Purpose'],
        ['route' => 'movements.pending-review', 'label' => 'Pending Review', 'badge' => $pendingReview, 'can' => 'movement.approve'],
        ['route' => 'movements.log', 'label' => 'Movement Log'],
    ]],
    ['key' => 'exchange', 'label' => 'Exchange & Refinery', 'icon' => 'flame', 'can' => 'exchange.manage', 'items' => [
        ['route' => 'exchange.new', 'label' => 'New Exchange'],
        ['route' => 'exchange.tracker', 'label' => 'Status Tracker'],
        ['route' => 'exchange.valuation', 'label' => 'Final Valuation'],
        ['route' => 'exchange.refinery.send', 'label' => 'Refinery Send'],
        ['route' => 'exchange.refinery.return', 'label' => 'Refinery Return'],
    ]],
    ['key' => 'orders', 'label' => 'Custom Orders', 'icon' => 'clipboard', 'can' => 'orders.manage', 'items' => [
        ['route' => 'orders.new', 'label' => 'New Order'],
        ['route' => 'orders.board', 'label' => 'Status Board'],
        ['route' => 'orders.reminders', 'label' => 'Ready Reminders'],
    ]],
    ['key' => 'pricing', 'label' => 'Pricing & Rates', 'icon' => 'trending-up', 'items' => [
        ['route' => 'pricing.rates', 'label' => 'Daily Rate Entry', 'can' => 'rate.update'],
        ['route' => 'pricing.simulator', 'label' => 'Price Simulator', 'can' => 'rate.update'],
        ['route' => 'pricing.rates.history', 'label' => 'Rate History', 'can' => 'rate.update'],
        ['route' => 'pricing.making-charges', 'label' => 'Making Charges', 'can' => 'rate.update'],
        ['route' => 'pricing.discounts', 'label' => 'Discount Rules', 'can' => 'discount.manage'],
        ['route' => 'pricing.additional-charges', 'label' => 'Additional Charges', 'can' => 'rate.update'],
    ]],
    ['key' => 'sales', 'label' => 'Sales & Billing', 'icon' => 'receipt', 'items' => [
        ['route' => 'sales.new', 'label' => 'New Sale'],
        ['route' => 'sales.verification', 'label' => 'Verification Queue', 'badge' => $pendingSales, 'can' => 'sale.approve'],
        ['route' => 'sales.history', 'label' => 'Sales History'],
    ]],
    ['key' => 'purchases', 'label' => 'Purchases', 'icon' => 'cart', 'can' => 'purchase.manage', 'items' => [
        ['route' => 'purchases.new', 'label' => 'New Purchase'],
        ['route' => 'purchases.list', 'label' => 'Purchase List'],
    ]],
    ['type' => 'link', 'route' => 'notifications.queue', 'label' => 'Messages', 'icon' => 'bell', 'badge' => $pendingMessages],
    ['key' => 'installments', 'label' => 'Installments', 'icon' => 'calendar', 'can' => 'customer.manage', 'items' => [
        ['route' => 'installments.enrol', 'label' => 'Scheme Enrolment'],
        ['route' => 'installments.monthly-status', 'label' => 'Monthly Status'],
        ['route' => 'installments.list', 'label' => 'Scheme List'],
    ]],
    ['key' => 'website', 'label' => 'Website', 'icon' => 'globe', 'can' => 'website.manage', 'items' => [
        ['route' => 'website.listings', 'label' => 'Listings'],
        // Website categories now come from Stock > Categories (8 Oct change list, 5.2).
        ['route' => 'website.collections', 'label' => 'Collections'],
        ['route' => 'website.settings', 'label' => 'Settings'],
    ]],
    ['key' => 'reports', 'label' => 'Reports', 'icon' => 'bar-chart', 'can' => 'audit.view', 'items' => [
        ['route' => 'reports.logbook', 'label' => 'Daily Logbook'],
        ['route' => 'reports.staff-activity', 'label' => 'Staff Activity'],
        ['route' => 'reports.location', 'label' => 'Location Report'],
        ['route' => 'ledgers.index', 'label' => 'Ledgers', 'can' => 'movement.create'],
    ]],
    ['key' => 'admin', 'label' => 'Administration', 'icon' => 'shield-check', 'items' => [
        ['route' => 'admin.customers', 'label' => 'Customers', 'can' => 'customer.manage'],
        ['route' => 'admin.employees', 'label' => 'Employees', 'can' => 'employee.manage'],
        ['route' => 'admin.users', 'label' => 'Users', 'can' => 'user.manage'],
        ['route' => 'admin.parties', 'label' => 'Karigars & Centres', 'can' => 'purchase.manage'],
        ['route' => 'admin.locations', 'label' => 'Locations', 'can' => 'location.manage'],
        ['route' => 'admin.roles', 'label' => 'Roles & Permissions', 'can' => 'role.manage'],
        ['route' => 'admin.referrals', 'label' => 'Referrals', 'can' => 'referral.manage'],
        ['route' => 'admin.audit-log', 'label' => 'Audit Log', 'can' => 'audit.view'],
    ]],
];

$allowed = function (array $entry) use ($user): bool {
    if (empty($entry['can'])) {
        return true;
    }
    return $user && $user->canAny((array) $entry['can']);
};

// Drop entries the user can't see or whose route doesn't exist, and empty groups.
$nav = collect($nav)
    ->filter($allowed)
    ->map(function ($entry) use ($allowed) {
        if (isset($entry['items'])) {
            $entry['items'] = array_values(array_filter($entry['items'], fn ($i) => $allowed($i) && Route::has($i['route'])));
        }
        return $entry;
    })
    ->filter(fn ($e) => isset($e['items']) ? count($e['items']) > 0 : Route::has($e['route']))
    ->values();

// Exact route match wins; otherwise fall back to child routes (e.g. stock.items.show -> Inventory).
$allRoutes = $nav->flatMap(fn ($e) => isset($e['items']) ? array_column($e['items'], 'route') : [$e['route']]);
$exact = $allRoutes->first(fn ($r) => request()->routeIs($r));
$activeRoute = $exact ?? $allRoutes->first(fn ($r) => request()->routeIs($r . '.*'));
@endphp

<aside
    class="app-sidebar fixed inset-y-0 left-0 z-drawer lg:z-sidebar flex flex-col bg-ink ink-grain text-ink-fg border-r border-black/40
           -translate-x-full lg:transform-none"
    :class="mobileNav ? '!translate-x-0 shadow-modal' : ''"
    aria-label="Main navigation">

    {{-- Brand --}}
    <div class="h-16 shrink-0 flex items-center gap-3 px-5 sb-center border-b border-ink-line/70">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0 group">
            <x-ui.logo :size="40" class="w-10 h-10 drop-shadow-[0_2px_8px_rgba(212,175,90,.28)] transition-transform duration-300 group-hover:scale-[1.04]" />
            <div class="sb-hide min-w-0 leading-none">
                <div class="font-display text-[21px] font-semibold text-white tracking-[0.01em]">Radharani</div>
                <div class="text-[10px] font-semibold tracking-[0.22em] text-gold-light/80 mt-1">JEWELLERY WORKS</div>
            </div>
        </a>
        <button type="button" class="lg:hidden ml-auto w-8 h-8 rounded-lg text-ink-dim hover:text-white hover:bg-white/5 flex items-center justify-center"
            x-on:click="mobileNav = false" aria-label="Close navigation">
            <x-ui.icon name="x" :size="17" />
        </button>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto overflow-x-hidden px-3 py-4 space-y-1 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
        @foreach ($nav as $entry)
            @if (($entry['type'] ?? null) === 'link')
                @php $isActive = $activeRoute === $entry['route']; @endphp
                <div class="relative" x-data="railTip()" x-on:mouseenter="show($el)" x-on:mouseleave="hide()">
                    <a href="{{ route($entry['route']) }}"
                       class="sb-group-btn sb-center {{ $isActive ? '!bg-gold/[.12] !text-gold-light' : '' }}"
                       @if($isActive) aria-current="page" @endif>
                        <span class="relative shrink-0">
                            <x-ui.icon :name="$entry['icon']" :size="18" class="{{ $isActive ? 'text-gold-light' : 'text-ink-dim' }}" />
                            @if (! empty($entry['badge']))
                                <span class="sb-only-collapsed absolute -top-1 -right-1 w-2 h-2 rounded-full bg-gold-light ring-2 ring-ink"></span>
                            @endif
                        </span>
                        <span class="sb-hide flex-1 truncate">{{ $entry['label'] }}</span>
                        @if (! empty($entry['badge']))
                            <span class="sb-hide min-w-[22px] h-5 px-1.5 rounded-full bg-gold/20 text-gold-light text-[11px] font-bold tabular flex items-center justify-center">{{ $entry['badge'] > 99 ? '99+' : $entry['badge'] }}</span>
                        @endif
                    </a>
                    <div x-show="hovering" x-cloak x-transition.opacity.duration.120ms :style="`top:${top}px`" class="fixed left-[66px] pl-3 z-dropdown">
                        <div class="bg-ink-soft ring-1 ring-ink-line rounded-lg shadow-pop px-3 py-2 text-[12.5px] font-semibold text-white whitespace-nowrap">{{ $entry['label'] }}</div>
                    </div>
                </div>
            @else
                @php
                    $groupRoutes = array_column($entry['items'], 'route');
                    $isCurrent = in_array($activeRoute, $groupRoutes, true);
                    $groupBadge = array_sum(array_map(fn ($i) => (int) ($i['badge'] ?? 0), $entry['items']));
                @endphp
                <div x-data="{ open: {{ $isCurrent ? 'true' : "\$persist(false).as('sb-group-{$entry['key']}')" }}, ...railTip() }"
                     x-on:mouseenter="show($el)" x-on:mouseleave="hide()" class="relative">
                    <button type="button" x-on:click="if (!railMode()) open = !open"
                        class="sb-group-btn sb-center {{ $isCurrent ? 'is-current' : '' }}"
                        :aria-expanded="open.toString()">
                        <span class="relative shrink-0 w-[18px] h-[18px] flex items-center justify-center rounded-md {{ $isCurrent ? 'text-gold-light' : 'text-ink-dim' }}">
                            <x-ui.icon :name="$entry['icon']" :size="18" />
                            @if ($isCurrent)
                                <span class="sb-only-collapsed absolute -left-[21px] top-1/2 -translate-y-1/2 w-[3px] h-5 rounded-r-full bg-gold-light"></span>
                            @endif
                            @if ($groupBadge)
                                <span class="sb-only-collapsed absolute -top-1 -right-1 w-2 h-2 rounded-full bg-gold-light ring-2 ring-ink"></span>
                            @endif
                        </span>
                        <span class="sb-hide flex-1 text-left truncate">{{ $entry['label'] }}</span>
                        @if ($groupBadge)
                            <span class="sb-hide min-w-[22px] h-5 px-1.5 rounded-full bg-gold/20 text-gold-light text-[11px] font-bold tabular flex items-center justify-center" x-show="!open">{{ $groupBadge > 99 ? '99+' : $groupBadge }}</span>
                        @endif
                        <x-ui.icon name="chevron-down" :size="14" class="sb-hide shrink-0 text-ink-dim transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                    </button>

                    {{-- Expanded sidebar: accordion children --}}
                    <div x-show="open" x-collapse class="sb-hide">
                        <div class="relative ml-[21px] pl-3 mt-0.5 mb-1.5 space-y-0.5 border-l border-ink-line">
                            @foreach ($entry['items'] as $item)
                                @php $itemActive = $activeRoute === $item['route']; @endphp
                                <a href="{{ route($item['route']) }}" class="sb-link {{ $itemActive ? 'is-active' : '' }}" @if($itemActive) aria-current="page" @endif>
                                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                    @if (! empty($item['badge']))
                                        <span class="min-w-[20px] h-[18px] px-1.5 rounded-full bg-gold/20 text-gold-light text-[10.5px] font-bold tabular flex items-center justify-center">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>

                    {{-- Collapsed rail: hover flyout with the group's pages --}}
                    <div x-show="railMode() && hovering" x-cloak
                         x-transition:enter="transition ease-silk duration-150" x-transition:enter-start="opacity-0 -translate-x-1" x-transition:enter-end="opacity-100 translate-x-0"
                         :style="`top:${top}px`" class="fixed left-[66px] pl-3 z-dropdown hidden lg:block">
                        <div class="w-56 bg-ink-soft ring-1 ring-ink-line rounded-xl shadow-pop p-1.5">
                            <div class="px-2.5 pt-1.5 pb-2 text-[11px] font-bold uppercase tracking-[0.14em] text-gold-light/80">{{ $entry['label'] }}</div>
                            @foreach ($entry['items'] as $item)
                                @php $itemActive = $activeRoute === $item['route']; @endphp
                                <a href="{{ route($item['route']) }}"
                                   class="flex items-center gap-2 h-9 px-2.5 rounded-lg text-[13px] font-medium {{ $itemActive ? 'bg-gold/[.12] text-gold-light' : 'text-ink-fg hover:bg-white/5 hover:text-white' }}">
                                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                    @if (! empty($item['badge']))
                                        <span class="min-w-[20px] h-[18px] px-1.5 rounded-full bg-gold/20 text-gold-light text-[10.5px] font-bold flex items-center justify-center">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    {{-- User --}}
    <div class="shrink-0 border-t border-ink-line/70 p-3">
        <div class="flex items-center gap-3 p-2 rounded-xl sb-center hover:bg-white/[.04] transition-colors">
            <div class="w-9 h-9 shrink-0 rounded-full bg-ink-soft ring-1 ring-gold/40 text-gold-light flex items-center justify-center font-display text-[17px] font-semibold">
                {{ strtoupper(mb_substr($user->name ?? 'U', 0, 1)) }}
            </div>
            <div class="sb-hide min-w-0 flex-1">
                <div class="text-[13px] font-semibold text-white truncate">{{ $user->name ?? 'Staff' }}</div>
                <div class="text-[11.5px] text-ink-dim truncate capitalize">{{ str_replace('_', ' ', $user?->getRoleNames()->first() ?? 'staff') }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="sb-hide">
                @csrf
                <button type="submit" aria-label="Log out" title="Log out"
                    class="w-8 h-8 rounded-lg text-ink-dim hover:text-white hover:bg-white/5 flex items-center justify-center">
                    <x-ui.icon name="log-out" :size="16" />
                </button>
            </form>
        </div>
    </div>
</aside>
