@php
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

$g = fn ($w) => number_format((float) $w, (float) $w >= 100 ? 1 : 2) . ' g';
$metalNames = ['gold' => 'Gold', 'silver' => 'Silver', 'platinum' => 'Platinum', 'titanium' => 'Titanium'];
$toneIcon = [
    'danger' => 'bg-danger-bg text-danger ring-danger/15',
    'warning' => 'bg-warning-bg text-warning ring-warning/15',
    'info' => 'bg-info-bg text-info ring-info/15',
    'neutral' => 'bg-surface-muted text-ink_text-secondary ring-line',
];
$urgent = collect($attention)->where('tone', 'danger')->count();
@endphp
<div class="space-y-6">

    {{-- Hero: greeting, what needs doing, today's rates --}}
    <section class="relative overflow-hidden rounded-[18px] bg-ink ink-grain text-ink-fg shadow-raised animate-rise-in">
        <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 w-[380px] h-[380px] rounded-full border border-gold/10"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 w-[240px] h-[240px] rounded-full border border-gold/10"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-end justify-between gap-6 p-6 sm:p-7 lg:p-8">
            <div class="min-w-0">
                <div class="text-[11px] font-semibold tracking-[0.2em] uppercase text-gold-light/80">{{ now()->format('l, j F Y') }}</div>
                <h1 class="font-display text-[34px] sm:text-[40px] leading-[1.05] font-semibold text-white mt-2">{{ $greeting }}</h1>
                <p class="text-[13.5px] text-ink-fg mt-2 max-w-[60ch]">
                    @if (count($attention) === 0)
                        Everything is up to date. Nothing is waiting on you right now.
                    @else
                        {{ count($attention) }} {{ count($attention) === 1 ? 'thing needs' : 'things need' }} your attention{!! $urgent ? ', <span class="text-[#F2A0A0]">' . $urgent . ' urgent</span>' : '' !!}.
                        <a href="#attention" class="text-gold-light hover:text-white underline decoration-gold/40 underline-offset-4">See the list</a>
                    @endif
                </p>
            </div>

            @if ($rates->isNotEmpty())
                <div class="shrink-0">
                    <div class="flex items-center justify-between gap-4 mb-2.5">
                        <span class="text-[11px] font-semibold tracking-[0.16em] uppercase text-ink-dim">Today's rates · per gram</span>
                        @if ($ratesStale)
                            @if ($canRates)
                                <a href="{{ route('pricing.rates') }}" class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full bg-warning/20 text-gold-light text-[11px] font-bold hover:bg-warning/30">
                                    <x-ui.icon name="alert-triangle" :size="12" /> Not updated today
                                </a>
                            @else
                                <span class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full bg-warning/20 text-gold-light text-[11px] font-bold">
                                    <x-ui.icon name="alert-triangle" :size="12" /> Not updated today
                                </span>
                            @endif
                        @else
                            <span class="text-[11px] text-ink-dim">Set {{ $rates->first()['at']->format('g:i a') }}</span>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-px rounded-xl overflow-hidden bg-ink-line/80 ring-1 ring-ink-line">
                        @foreach ($rates as $r)
                            <div class="bg-ink-soft/95 px-4 py-3 min-w-[132px]">
                                <div class="flex items-center gap-2 text-[12px] text-ink-dim">
                                    <x-movement.metal-dot :metal="$r['metal']" class="!ring-ink-soft" /> {{ $metalNames[$r['metal']] }}
                                </div>
                                <div class="font-display text-[24px] leading-tight font-semibold text-white mt-1">
                                    ₹{{ number_format($r['rate'], $r['rate'] < 1000 ? 2 : 0) }}
                                </div>
                                <div class="text-[11.5px] font-semibold mt-0.5 tabular">
                                    @if ($r['change'] === null || abs($r['change']) < 0.005)
                                        <span class="text-ink-dim">No change</span>
                                    @elseif ($r['change'] > 0)
                                        <span class="text-[#6FD3A5]">↑ ₹{{ number_format($r['change'], abs($r['change']) < 10 ? 2 : 0) }} <span class="opacity-75">({{ number_format($r['pct'], 1) }}%)</span></span>
                                    @else
                                        <span class="text-[#F2A0A0]">↓ ₹{{ number_format(abs($r['change']), abs($r['change']) < 10 ? 2 : 0) }} <span class="opacity-75">({{ number_format(abs($r['pct']), 1) }}%)</span></span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif ($canRates)
                <x-ui.button variant="primary" icon="coins" :href="route('pricing.rates')">Enter today's rates</x-ui.button>
            @endif
        </div>
    </section>

    {{-- Quick actions --}}
    @php
        // Literal class names so Tailwind emits them; one row on wide screens whatever the user can see.
        $actionCols = 'sm:grid-cols-4 xl:grid-cols-6';
    @endphp
    <nav aria-label="Quick actions" class="grid grid-cols-2 {{ $actionCols }} gap-3">
        @foreach ($actions as $a)
            @php $tileClass = 'press group flex items-center xl:flex-col xl:items-start gap-3 bg-white border border-line-light rounded-card shadow-card px-4 py-3.5 text-left transition-[border-color,box-shadow] duration-200 hover:border-gold-soft hover:shadow-raised'; @endphp
            @if (! empty($a['scan']))
                <button type="button" x-on:click="$dispatch('rj-scan', { mode: 'go' })" class="{{ $tileClass }}">
            @else
                <a href="{{ $a['href'] }}" class="{{ $tileClass }}">
            @endif
                <span class="w-9 h-9 shrink-0 rounded-[10px] flex items-center justify-center transition-colors {{ $loop->first ? 'gold-sheen text-white shadow-gold' : 'bg-gold-tint text-gold-dark ring-1 ring-inset ring-gold-soft/60 group-hover:bg-[#F6EAD0]' }}">
                    <x-ui.icon :name="$a['icon']" :size="16" />
                </span>
                <span class="text-[13px] font-semibold text-ink_text-primary leading-tight">{{ $a['label'] }}</span>
                @if (isset($a['count']))
                    <span class="ml-auto xl:ml-0 text-[11.5px] font-semibold tabular {{ $a['count'] ? 'text-gold-dark' : 'text-ink_text-muted' }}">{{ $a['count'] }} today</span>
                @endif
            @if (! empty($a['scan']))
                </button>
            @else
                </a>
            @endif
        @endforeach
    </nav>

    {{-- Headline figures --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <x-ui.stat-card icon="gem" label="Pieces the shop owns" :href="Route::has('stock.items') && auth()->user()->can('stock.manage') ? route('stock.items') : null"
            :value="number_format($stock['owned'])"
            :hint="$g($stock['ownedWeight']) . ' across all metals'" />
        <x-ui.stat-card icon="layers" label="On the counter" :href="route('movements.vault-counter')"
            :value="number_format($stock['counter'])" hint="Out of the vault right now" />
        <x-ui.stat-card icon="truck" label="Out of the shop"
            :value="number_format($stock['outCount'])"
            :hint="$stock['overdue'] ? $stock['overdue'] . ' overdue' : 'Karigar, hallmarking and others'" />
    </div>

    {{-- Attention --}}
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start">
        <x-ui.card id="attention" class="scroll-mt-24 xl:col-span-12" title="Needs attention" icon="bell"
            :subtitle="count($attention) ? 'Most urgent first' : null" :padding="false">
            @if (count($attention))
                <x-slot:actions>
                    <x-ui.badge :tone="$urgent ? 'danger' : 'gold'">{{ count($attention) }}</x-ui.badge>
                </x-slot:actions>
                <ul @class(['divide-y divide-line-light', 'grid sm:grid-cols-2 lg:grid-cols-3 sm:divide-y-0'])>
                    @foreach ($attention as $a)
                        <li>
                            <a href="{{ $a['href'] }}" class="group flex items-center gap-3.5 px-5 py-3.5 hover:bg-surface-sunken transition-colors">
                                <span class="w-9 h-9 shrink-0 rounded-[10px] ring-1 ring-inset flex items-center justify-center {{ $toneIcon[$a['tone']] }}">
                                    <x-ui.icon :name="$a['icon']" :size="16" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13.5px] font-semibold text-ink_text-primary leading-snug">{{ $a['title'] }}</span>
                                    <span class="block text-[12px] text-ink_text-secondary leading-snug mt-0.5">{{ $a['detail'] }}</span>
                                </span>
                                @unless (! empty($a['hideCount']))
                                    <span class="font-display text-[24px] leading-none font-semibold text-ink_text-primary tabular">{{ $a['count'] }}</span>
                                @endunless
                                <x-ui.icon name="chevron-right" :size="15" class="shrink-0 text-ink_text-muted group-hover:text-gold-dark group-hover:translate-x-0.5 transition-transform" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-ui.empty-state icon="check-circle" title="All caught up" message="No sales to verify, returns to review or overdue pieces." compact />
            @endif
        </x-ui.card>
    </div>

    {{-- Stock, work in progress, activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4 items-start">

        <x-ui.card title="Where your stock is" icon="map-pin"
            :subtitle="number_format($stock['owned']) . ' pieces · ' . $g($stock['ownedWeight'])">
            @if (auth()->user()->can('audit.view') && Route::has('reports.location'))
                <x-slot:actions>
                    <x-ui.button variant="ghost" size="xs" :href="route('reports.location')" icon-right="arrow-right">Report</x-ui.button>
                </x-slot:actions>
            @endif

            @php $maxLoc = max(1, $stock['locations']->max('count') ?? 1); @endphp
            @if ($stock['locations']->isEmpty())
                <p class="text-[13px] text-ink_text-muted">No pieces recorded yet.</p>
            @else
                <ul class="space-y-3.5">
                    @foreach ($stock['locations'] as $key => $loc)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-[13px]">
                                <span class="text-ink_text-primary font-medium flex items-center gap-2">
                                    {{ $loc['label'] }}
                                    @if ($loc['overdue'])
                                        <x-ui.badge tone="danger" size="sm" dot>{{ $loc['overdue'] }} overdue</x-ui.badge>
                                    @endif
                                </span>
                                <span class="shrink-0 tabular">
                                    <span class="font-semibold text-ink_text-primary">{{ number_format($loc['count']) }}</span>
                                    <span class="text-ink_text-muted text-[12px] ml-1.5">{{ $g($loc['weight']) }}</span>
                                </span>
                            </div>
                            <div class="mt-1.5 h-1.5 rounded-full bg-surface-muted overflow-hidden" title="{{ $loc['label'] }}: {{ $loc['count'] }} pieces">
                                <div class="h-full rounded-full {{ $key === 'vault' || $key === 'counter' ? 'bg-gold' : 'bg-gold-light' }}" style="width: {{ max(2, round($loc['count'] / $maxLoc * 100, 1)) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($stock['byMetal']->isNotEmpty())
                <div class="mt-5 pt-4 border-t border-line-light grid grid-cols-2 gap-3">
                    @foreach ($stock['byMetal'] as $m)
                        <div class="rounded-control bg-surface-sunken px-3 py-2.5">
                            <div class="flex items-center gap-2 text-[12px] text-ink_text-secondary">
                                <x-movement.metal-dot :metal="$m['metal']" /> {{ $metalNames[$m['metal']] ?? ucfirst($m['metal']) }}
                                <span class="ml-auto text-ink_text-muted tabular">{{ $m['count'] }} pcs</span>
                            </div>
                            <div class="font-display text-[19px] leading-tight font-semibold text-ink_text-primary mt-1">{{ $g($m['weight']) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Work in progress" icon="clipboard" subtitle="Orders, exchanges and material out">
            @php
                $orderSteps = ['placed' => 'Placed', 'confirmed' => 'Confirmed', 'ready' => 'Ready'];
                $exchangeSteps = ['received' => 'Received', 'melted' => 'Melted', 'tested' => 'Tested', 'valued' => 'Valued'];
            @endphp

            @if ($pipeline['orders'] !== null)
                <div class="flex items-center justify-between mb-2.5">
                    <h4 class="text-[12px] font-bold uppercase tracking-[0.12em] text-ink_text-muted">Custom orders</h4>
                    <a href="{{ route('orders.board') }}" class="text-[12px] font-semibold text-gold-dark hover:text-gold">Board</a>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($orderSteps as $key => $label)
                        <a href="{{ route('orders.board') }}" class="rounded-control border border-line-light px-3 py-2.5 hover:border-gold-soft transition-colors {{ $key === 'ready' && ($pipeline['orders'][$key] ?? 0) ? 'bg-gold-tint border-gold-soft/70' : 'bg-white' }}">
                            <div class="font-display text-[24px] leading-none font-semibold text-ink_text-primary">{{ $pipeline['orders'][$key] ?? 0 }}</div>
                            <div class="text-[11.5px] text-ink_text-secondary mt-1">{{ $label }}</div>
                        </a>
                    @endforeach
                </div>

                @if ($pipeline['dueSoon']->isNotEmpty())
                    <ul class="mt-3 divide-y divide-line-light">
                        @foreach ($pipeline['dueSoon'] as $o)
                            @php $late = $o->expected_ready_date->isBefore(today()); @endphp
                            <li>
                                <a href="{{ route('orders.show', $o) }}" class="flex items-center gap-3 py-2.5 group">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-medium text-ink_text-primary truncate group-hover:text-gold-dark">{{ $o->product_description }}</span>
                                        <span class="block text-[11.5px] text-ink_text-muted truncate">{{ $o->customer->name ?? 'Customer' }}</span>
                                    </span>
                                    <x-ui.badge :tone="$late ? 'danger' : ($o->expected_ready_date->isToday() ? 'warning' : 'neutral')" size="sm">
                                        {{ $late ? 'Late · ' : 'Due ' }}{{ $o->expected_ready_date->isToday() ? 'today' : $o->expected_ready_date->format('j M') }}
                                    </x-ui.badge>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif

            @if ($pipeline['exchange'] !== null)
                <div @class(['flex items-center justify-between mb-2.5', 'mt-5 pt-4 border-t border-line-light' => $pipeline['orders'] !== null])>
                    <h4 class="text-[12px] font-bold uppercase tracking-[0.12em] text-ink_text-muted">Old gold exchange</h4>
                    <a href="{{ route('exchange.tracker') }}" class="text-[12px] font-semibold text-gold-dark hover:text-gold">Tracker</a>
                </div>
                <ol class="flex items-stretch">
                    @foreach ($exchangeSteps as $key => $label)
                        @php $n = $pipeline['exchange'][$key] ?? 0; @endphp
                        <li class="flex-1 min-w-0 relative">
                            <div class="flex items-center">
                                <span @class([
                                    'w-7 h-7 shrink-0 rounded-full flex items-center justify-center text-[12px] font-bold tabular ring-1 ring-inset',
                                    'gold-sheen text-white ring-gold-dark/30' => $n > 0,
                                    'bg-surface-muted text-ink_text-muted ring-line' => $n === 0,
                                ])>{{ $n }}</span>
                                @unless ($loop->last)<span class="flex-1 h-px bg-line mx-1.5"></span>@endunless
                            </div>
                            <div class="text-[11.5px] text-ink_text-secondary mt-1.5">{{ $label }}</div>
                        </li>
                    @endforeach
                </ol>
            @endif

            <div @class(['grid grid-cols-3 gap-2', 'mt-5 pt-4 border-t border-line-light' => $pipeline['orders'] !== null || $pipeline['exchange'] !== null])>
                <a href="{{ route('movements.karigar-return') }}" class="rounded-control bg-surface-sunken px-3 py-2.5 hover:bg-surface-muted transition-colors">
                    <div class="font-display text-[20px] leading-none font-semibold text-ink_text-primary">{{ $pipeline['karigarRaw'] }}</div>
                    <div class="text-[11.5px] text-ink_text-secondary mt-1 leading-snug">Raw batches at karigar</div>
                </a>
                <a href="{{ route('movements.karigar-return') }}" class="rounded-control bg-surface-sunken px-3 py-2.5 hover:bg-surface-muted transition-colors">
                    <div class="font-display text-[20px] leading-none font-semibold text-ink_text-primary">{{ $pipeline['customerJobs'] }}</div>
                    <div class="text-[11.5px] text-ink_text-secondary mt-1 leading-snug">Customer repairs out</div>
                </a>
                @if ($pipeline['refinery'] !== null)
                    <a href="{{ route('exchange.refinery.return') }}" class="rounded-control bg-surface-sunken px-3 py-2.5 hover:bg-surface-muted transition-colors">
                        <div class="font-display text-[20px] leading-none font-semibold text-ink_text-primary">{{ $pipeline['refinery'] }}</div>
                        <div class="text-[11.5px] text-ink_text-secondary mt-1 leading-snug">Batches at refinery</div>
                    </a>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card title="Recent activity" icon="history" :padding="false" class="lg:col-span-2 xl:col-span-1">
            @if (Route::has('movements.log'))
                <x-slot:actions>
                    <x-ui.button variant="ghost" size="xs" :href="route('movements.log')" icon-right="arrow-right">Movement log</x-ui.button>
                </x-slot:actions>
            @endif
            @if ($activity->isEmpty())
                <x-ui.empty-state icon="history" title="Nothing yet" message="Movements and sales will appear here as they happen." compact />
            @else
                <ol class="px-5 py-2">
                    @foreach ($activity as $e)
                        <li class="relative flex gap-3.5 py-2.5">
                            @unless ($loop->last)<span aria-hidden="true" class="absolute left-[15px] top-10 bottom-[-6px] w-px bg-line-light"></span>@endunless
                            <span @class([
                                'relative w-8 h-8 shrink-0 rounded-full ring-1 ring-inset flex items-center justify-center',
                                'bg-gold-tint text-gold-dark ring-gold-soft/70' => $e['icon'] === 'receipt',
                                'bg-surface-sunken text-ink_text-secondary ring-line' => $e['icon'] !== 'receipt',
                            ])>
                                <x-ui.icon :name="$e['icon']" :size="14" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="text-[13px] font-semibold text-ink_text-primary truncate">{{ $e['title'] }}</span>
                                    <time class="shrink-0 text-[11.5px] text-ink_text-muted" datetime="{{ $e['at']?->toIso8601String() }}" title="{{ $e['at']?->format('j M Y, g:i a') }}">{{ $e['at']?->isToday() ? $e['at']->format('g:i a') : $e['at']?->format('j M') }}</time>
                                </div>
                                <div class="text-[12px] text-ink_text-secondary truncate mt-0.5">
                                    @if ($e['url'])
                                        <a href="{{ $e['url'] }}" class="{{ $e['icon'] === 'receipt' ? 'font-semibold' : 'rj-code' }} text-ink_text-primary hover:text-gold-dark">{{ $e['code'] }}</a>
                                    @else
                                        <span class="{{ $e['icon'] === 'receipt' ? 'font-semibold' : 'rj-code' }} text-ink_text-primary">{{ $e['code'] }}</span>
                                    @endif
                                    @if ($e['detail']) · {{ $e['detail'] }} @endif
                                    <span class="text-ink_text-muted">· {{ $e['by'] }}</span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-ui.card>
    </div>
</div>
