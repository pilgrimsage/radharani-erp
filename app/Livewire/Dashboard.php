<?php
namespace App\Livewire;

use App\Models\Customer\CustomerMaterialJob;
use App\Models\Customer\InstallmentScheme;
use App\Models\Exchange\ExchangeTransaction;
use App\Models\Exchange\RefineryBatch;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Movement\Movement;
use App\Models\Movement\RateLog;
use App\Models\Notification\PendingNotification;
use App\Models\Orders\Order;
use App\Models\Purchase\PurchaseItem;
use App\Models\Sales\Sale;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Support\Trackables;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

/**
 * The landing page after staff sign in: today's rates, what needs someone's
 * attention, where the stock physically is.
 *
 * Read-only by design. Every number links to the screen where the work is
 * actually done, and each panel is gated on the same permission as that
 * screen, so counter staff see their operational view without the money.
 */
class Dashboard extends Component
{
    // Where a piece can be while it's out of the shop, in display order.
    private const OUT_PAIRS = [
        'karigar' => 'With karigars',
        'hallmark' => 'At hallmarking',
        'photo' => 'Out for photos',
        'custom' => 'Out (custom purpose)',
        'melt' => 'At melting',
    ];

    public function render()
    {
        $user = auth()->user();
        $can = fn (string ...$perms) => $user && $user->canAny($perms);

        $rates = $this->rates();
        $stock = $this->stock($rates);
        $attention = $this->attention($can, $stock, $rates);

        return view('livewire.dashboard', [
            'greeting' => $this->greeting(),
            'rates' => $rates,
            'ratesStale' => $rates->isNotEmpty() && ! $rates->first()['at']?->isToday(),
            'canRates' => $can('rate.update') && Route::has('pricing.rates'),
            'stock' => $stock,
            'attention' => $attention,
            'pipeline' => $this->pipeline($can),
            'activity' => $this->activity(),
            'actions' => $this->quickActions($can),
        ])->layout('components.layouts.app', ['title' => 'Dashboard · Radharani Jewellery']);
    }

    private function greeting(): string
    {
        $h = now()->hour;
        $part = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
        $name = str(auth()->user()->name ?? '')->before(' ')->toString();

        return $name ? "{$part}, {$name}" : $part;
    }

    // Latest rate per metal plus the change from the rate before it.
    private function rates()
    {
        return collect(['gold', 'silver', 'platinum', 'titanium'])
            ->map(function ($metal) {
                $logs = RateLog::where('metal', $metal)->latest('created_at')->latest('id')->limit(2)->get();
                if ($logs->isEmpty()) {
                    return null;
                }
                $now = (float) $logs[0]->rate;
                $prev = isset($logs[1]) ? (float) $logs[1]->rate : null;

                return [
                    'metal' => $metal,
                    'rate' => $now,
                    'at' => $logs[0]->created_at,
                    'change' => $prev !== null ? $now - $prev : null,
                    'pct' => $prev ? ($now - $prev) / $prev * 100 : null,
                ];
            })
            ->filter()
            ->keyBy('metal');
    }

    // Where every piece the shop still owns is right now, plus its metal value.
    private function stock($rates): array
    {
        $byStatus = Item::selectRaw('status, COUNT(*) as n, COALESCE(SUM(weight), 0) as w')
            ->groupBy('status')->get()->keyBy('status');
        $n = fn ($s) => (int) ($byStatus[$s]->n ?? 0);
        $w = fn ($s) => (float) ($byStatus[$s]->w ?? 0);

        // Metal value of everything not yet sold, at today's rate. Making charges
        // are left out on purpose: they're provisional (docs/REQUIREMENTS.md) and
        // pricing every piece individually on each page load doesn't scale.
        $owned = ['in_stock', 'dispatched', 'pending_review', 'reserved'];
        $byMetal = Item::whereIn('status', $owned)
            ->selectRaw("COALESCE(metal, 'gold') as m, COUNT(*) as n, COALESCE(SUM(weight), 0) as w")
            ->groupBy('m')->get()
            ->map(fn ($r) => [
                'metal' => $r->m,
                'count' => (int) $r->n,
                'weight' => (float) $r->w,
            ])->sortByDesc('weight')->values();

        // On the counter: the latest vault movement of the piece (or of its
        // packet / box) is a vault_out, and the piece is still in stock.
        $latestVault = Movement::selectRaw('MAX(id)')
            ->whereIn('movement_type', Movement::PAIRS['vault'])
            ->groupBy('trackable_type', 'trackable_id');
        $onCounter = Movement::whereIn('id', $latestVault)->where('movement_type', 'vault_out')
            ->get(['trackable_type', 'trackable_id'])->groupBy('trackable_type')
            ->map(fn ($g) => $g->pluck('trackable_id')->all());
        $counterPackets = array_merge(
            $onCounter['packet'] ?? [],
            ! empty($onCounter['box']) ? Packet::whereIn('box_id', $onCounter['box'])->pluck('id')->all() : [],
        );
        $counter = Item::where('status', 'in_stock')
            ->where(fn ($q) => $q->whereIn('id', $onCounter['item'] ?? [0])->orWhereIn('packet_id', $counterPackets ?: [0]))
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(weight), 0) as w')->first();

        // Out of the shop: open item dispatches, grouped by where they went.
        $open = Movement::openItemDispatches(array_keys(self::OUT_PAIRS))
            ->get(['movement_type', 'trackable_id', 'expected_return']);
        $weights = Item::whereIn('id', $open->pluck('trackable_id'))->pluck('weight', 'id');
        $out = collect(self::OUT_PAIRS)->map(function ($label, $pair) use ($open, $weights) {
            $rows = $open->where('movement_type', $pair . '_out');

            return [
                'label' => $label,
                'count' => $rows->count(),
                'weight' => $rows->sum(fn ($m) => (float) ($weights[$m->trackable_id] ?? 0)),
                'overdue' => $rows->filter(fn ($m) => $m->expected_return && $m->expected_return->isBefore(today()))->count(),
            ];
        });

        $counterN = (int) $counter->n;
        $locations = collect([
            'vault' => ['label' => 'In the vault', 'count' => $n('in_stock') - $counterN, 'weight' => $w('in_stock') - (float) $counter->w, 'overdue' => 0],
            'counter' => ['label' => 'On the counter', 'count' => $counterN, 'weight' => (float) $counter->w, 'overdue' => 0],
        ])->merge($out)->merge([
            'review' => ['label' => 'Awaiting review', 'count' => $n('pending_review'), 'weight' => $w('pending_review'), 'overdue' => 0],
            'reserved' => ['label' => 'Held for a sale', 'count' => $n('reserved'), 'weight' => $w('reserved'), 'overdue' => 0],
        ]);

        // Dispatched pieces with no open item-level dispatch (sent out inside a
        // packet/box, or older data); keep them visible instead of dropping them.
        $unplaced = $n('dispatched') - $out->sum('count');
        if ($unplaced > 0) {
            $locations['other'] = ['label' => 'Dispatched (other)', 'count' => $unplaced, 'weight' => max(0, $w('dispatched') - $out->sum('weight')), 'overdue' => 0];
        }

        return [
            'owned' => array_sum(array_map($n, $owned)),
            'ownedWeight' => $byMetal->sum('weight'),
            'inStock' => $n('in_stock'),
            'sold' => $n('sold'),
            'counter' => $counterN,
            'outCount' => $n('dispatched'),
            'overdue' => $out->sum('overdue'),
            'overdueKarigar' => $out['karigar']['overdue'],
            'overdueHallmark' => $out['hallmark']['overdue'],
            'byMetal' => $byMetal,
            'locations' => $locations->filter(fn ($l) => $l['count'] > 0),
            'statusCounts' => $byStatus->map(fn ($r) => (int) $r->n)->all(),
            'statusTotal' => $byStatus->sum('n'),
        ];
    }

    // Queues that need a person to act, most urgent first. Only what the user
    // can actually open, and only what's non-empty.
    private function attention(callable $can, array $stock, $rates): array
    {
        $items = [];
        $add = function (bool $allowed, string $route, int $count, array $row) use (&$items) {
            if ($allowed && $count > 0 && Route::has($route)) {
                $items[] = $row + ['count' => $count, 'href' => route($route, $row['params'] ?? [])];
            }
        };

        $gold = $rates['gold'] ?? null;
        if ($gold && ! $gold['at']->isToday()) {
            $add($can('rate.update'), 'pricing.rates', 1, [
                'tone' => 'danger', 'icon' => 'coins', 'title' => "Enter today's metal rates",
                'detail' => 'Last set ' . $gold['at']->diffForHumans() . '. Prices use the old rate until then.',
                'hideCount' => true,
            ]);
        }

        $rawOverdue = KarigarRawBatch::where('status', '!=', 'returned')->whereDate('expected_return', '<', today())->count();
        $jobsOverdue = CustomerMaterialJob::where('status', 'out')->whereDate('expected_return', '<', today())->count();
        $karigarLate = $stock['overdueKarigar'] + $rawOverdue + $jobsOverdue;
        $add(true, 'movements.karigar-return', $karigarLate, [
            'tone' => 'danger', 'icon' => 'clock', 'title' => 'Overdue from karigars',
            'detail' => collect([
                $stock['overdueKarigar'] ? $stock['overdueKarigar'] . ' tagged ' . str('piece')->plural($stock['overdueKarigar']) : null,
                $rawOverdue ? $rawOverdue . ' raw-material ' . str('batch')->plural($rawOverdue) : null,
                $jobsOverdue ? $jobsOverdue . ' customer ' . str('job')->plural($jobsOverdue) : null,
            ])->filter()->join(', ') . ' past the expected date.',
        ]);
        $add(true, 'movements.hallmark-return', $stock['overdueHallmark'], [
            'tone' => 'danger', 'icon' => 'clock', 'title' => 'Overdue from hallmarking',
            'detail' => 'Pieces past their expected return date.',
        ]);

        // Everything physically outside the vault, wherever it is (counter, display,
        // karigar, hallmarking, photos, anything else), so nothing is out unnoticed.
        $outOfVault = $stock['locations']->only(['counter', 'karigar', 'hallmark', 'photo', 'custom', 'melt', 'other']);
        $add(true, 'reports.location', (int) $outOfVault->sum('count'), [
            'tone' => 'warning', 'icon' => 'layers', 'title' => 'Pieces out of the vault',
            'detail' => $outOfVault->map(fn ($l) => $l['count'] . ' ' . strtolower($l['label']))->implode(', ') . '.',
        ]);

        $unverified = Sale::where('confirmed_by_accountant', false);
        $add($can('sale.approve'), 'sales.verification', (clone $unverified)->count(), [
            'tone' => 'warning', 'icon' => 'receipt', 'title' => 'Sales to verify',
            'detail' => 'Held as reserved until confirmed.',
        ]);

        $add($can('movement.approve'), 'movements.pending-review', $stock['statusCounts']['pending_review'] ?? 0, [
            'tone' => 'warning', 'icon' => 'user-check', 'title' => 'Returns to review',
            'detail' => 'Back from karigar or hallmarking, not yet in stock.',
        ]);

        $add($can('movement.approve'), 'movements.pending-review', PurchaseItem::where('tag_pending', true)->whereNull('item_id')->count(), [
            'tone' => 'info', 'icon' => 'tag', 'title' => 'Purchase lines to tag',
            'detail' => 'Bought material that isn\'t a tagged piece yet.', 'params' => ['tab' => 'tags'],
        ]);

        $readyOrders = Order::where('status', 'ready')->pluck('id');
        $told = PendingNotification::where('related_type', 'order')->whereIn('related_id', $readyOrders)
            ->where('status', 'sent')->distinct()->pluck('related_id');
        $add($can('orders.manage'), 'orders.reminders', $readyOrders->diff($told)->count(), [
            'tone' => 'warning', 'icon' => 'bell', 'title' => 'Orders ready, customer not told',
            'detail' => 'Send the collection reminder.',
        ]);

        $add($can('orders.manage'), 'orders.board', Order::whereIn('status', ['placed', 'confirmed'])
            ->whereDate('expected_ready_date', '<', today())->count(), [
            'tone' => 'danger', 'icon' => 'clipboard', 'title' => 'Orders past their ready date',
            'detail' => 'Promised to the customer and still not ready.',
        ]);

        $add(true, 'notifications.queue', PendingNotification::where('status', 'pending')->count(), [
            'tone' => 'info', 'icon' => 'mail', 'title' => 'Messages to send',
            'detail' => 'Copy into WhatsApp or SMS, then mark sent.',
        ]);

        $unpaidSchemes = InstallmentScheme::where('status', 'active')
            ->whereDoesntHave('payments', fn ($q) => $q->whereYear('paid_on', now()->year)->whereMonth('paid_on', now()->month));
        $add($can('customer.manage'), 'installments.monthly-status', (clone $unpaidSchemes)->count(), [
            'tone' => 'info', 'icon' => 'calendar', 'title' => 'Instalments due this month',
            'detail' => 'Not yet paid for ' . now()->format('F') . '.',
        ]);

        $add($can('exchange.manage'), 'exchange.valuation', ExchangeTransaction::where('stage', 'tested')->count(), [
            'tone' => 'info', 'icon' => 'scale', 'title' => 'Exchanges ready to value',
            'detail' => 'Purity tested, waiting for the final valuation.',
        ]);

        $rank = ['danger' => 0, 'warning' => 1, 'info' => 2, 'neutral' => 3];
        usort($items, fn ($a, $b) => $rank[$a['tone']] <=> $rank[$b['tone']]);

        return $items;
    }

    private function pipeline(callable $can): array
    {
        $orders = $can('orders.manage')
            ? Order::whereIn('status', ['placed', 'confirmed', 'ready'])->selectRaw('status, COUNT(*) as n')
                ->groupBy('status')->pluck('n', 'status')
            : null;

        $dueSoon = $can('orders.manage')
            ? Order::with('customer:id,name')->whereIn('status', ['placed', 'confirmed'])
                ->whereNotNull('expected_ready_date')->whereDate('expected_ready_date', '<=', today()->addDays(7))
                ->orderBy('expected_ready_date')->limit(4)->get()
            : collect();

        $exchange = $can('exchange.manage')
            ? ExchangeTransaction::where('stage', '!=', 'settled')->selectRaw('stage, COUNT(*) as n')
                ->groupBy('stage')->pluck('n', 'stage')
            : null;

        return [
            'orders' => $orders,
            'dueSoon' => $dueSoon,
            'exchange' => $exchange,
            'refinery' => $can('exchange.manage') ? RefineryBatch::where('status', 'sent')->count() : null,
            'karigarRaw' => KarigarRawBatch::where('status', '!=', 'returned')->count(),
            'customerJobs' => CustomerMaterialJob::where('status', 'out')->count(),
        ];
    }

    // The latest few things that happened, movements and sales together.
    private function activity()
    {
        $movements = Movement::with('user:id,name')->latest('id')->limit(8)->get();
        $loaded = Trackables::load($movements);

        $rows = $movements->map(function ($m) use ($loaded) {
            $d = Trackables::describe($loaded, $m->trackable_type, $m->trackable_id);

            return [
                'at' => $m->created_at,
                'icon' => 'repeat',
                'title' => $m->label,
                'code' => $d['code'],
                'detail' => $m->counterparty ?: $m->purpose_label,
                'by' => $m->user->name ?? 'Unknown',
                'url' => $d['url'],
            ];
        });

        $sales = Sale::with(['customer:id,name', 'creator:id,name'])->latest('id')->limit(8)->get()
            ->map(fn ($s) => [
                'at' => $s->created_at,
                'icon' => 'receipt',
                'title' => $s->confirmed_by_accountant ? 'Sale verified' : 'Sale entered',
                'code' => $s->customer->name ?? 'Walk-in',
                'detail' => null,
                'by' => $s->creator->name ?? 'Unknown',
                'url' => Route::has('sales.invoice') ? route('sales.invoice', $s) : null,
            ]);

        return $rows->concat($sales)->sortByDesc('at')->take(8)->values();
    }

    // One tile per kind of entry. 'count' is today's entries where that is cheap to
    // know; tiles without one are plain "add" shortcuts.
    private function quickActions(callable $can): array
    {
        $moves = Movement::whereDate('created_at', today())->selectRaw('movement_type, COUNT(*) as n')
            ->groupBy('movement_type')->pluck('n', 'movement_type');
        $sum = fn (string ...$types) => (int) collect($types)->sum(fn ($t) => $moves[$t] ?? 0);

        return collect([
            ['route' => 'sales.new', 'label' => 'New sale', 'icon' => 'receipt', 'can' => 'sale.create',
                'count' => Sale::whereDate('created_at', today())->count()],
            ['scan' => true, 'label' => 'Scan a tag', 'icon' => 'scan'],
            ['route' => 'movements.vault-counter', 'label' => 'Vault ↔ Counter', 'icon' => 'repeat',
                'count' => $sum('vault_out', 'vault_in')],
            ['route' => 'movements.karigar-dispatch', 'label' => 'Karigar send', 'icon' => 'truck',
                'count' => $sum('karigar_out') + KarigarRawBatch::whereDate('created_at', today())->count()],
            ['route' => 'movements.karigar-return', 'label' => 'Karigar receive', 'icon' => 'package',
                'count' => $sum('karigar_in')],
            ['route' => 'movements.hallmark-dispatch', 'label' => 'Hallmark send', 'icon' => 'truck',
                'count' => $sum('hallmark_out')],
            ['route' => 'movements.hallmark-return', 'label' => 'Hallmark receive', 'icon' => 'package',
                'count' => $sum('hallmark_in')],
            ['route' => 'movements.custom-purpose', 'label' => 'Photo / other purpose', 'icon' => 'camera',
                'count' => $sum('photo_out', 'photo_in', 'custom_out', 'custom_in')],
            ['route' => 'movements.pending-review', 'label' => 'Pending review', 'icon' => 'user-check', 'can' => 'movement.approve'],
            ['route' => 'orders.new', 'label' => 'New order', 'icon' => 'clipboard', 'can' => 'orders.manage',
                'count' => Order::whereDate('created_at', today())->count()],
            ['route' => 'exchange.new', 'label' => 'Old gold exchange', 'icon' => 'flame', 'can' => 'exchange.manage',
                'count' => ExchangeTransaction::whereDate('created_at', today())->count()],
            ['route' => 'exchange.refinery.send', 'label' => 'Refinery send', 'icon' => 'flame', 'can' => 'exchange.manage',
                'count' => RefineryBatch::whereDate('sent_at', today())->count()],
            ['route' => 'exchange.refinery.return', 'label' => 'Refinery receive', 'icon' => 'flame', 'can' => 'exchange.manage',
                'count' => RefineryBatch::whereDate('returned_at', today())->count()],
            ['route' => 'purchases.new', 'label' => 'Raw-material purchase', 'icon' => 'cart', 'can' => 'purchase.manage'],
            ['route' => 'pricing.rates', 'label' => 'Daily rates', 'icon' => 'coins', 'can' => 'rate.update'],
            ['route' => 'stock.items', 'label' => 'Inventory', 'icon' => 'gem', 'can' => 'stock.manage'],
        ])
            ->filter(fn ($a) => (empty($a['can']) || $can($a['can'])) && (! empty($a['scan']) || Route::has($a['route'])))
            ->map(fn ($a) => $a + ['href' => isset($a['route']) ? route($a['route']) : null])
            ->values()->all();
    }

    // Rupees in the lakh/crore shorthand the shop actually speaks in.
    public static function inr(float $v): string
    {
        $abs = abs($v);
        $sign = $v < 0 ? '-' : '';

        return $sign . match (true) {
            $abs >= 1e7 => rtrim(rtrim(number_format($abs / 1e7, 2), '0'), '.') . ' Cr',
            $abs >= 1e5 => rtrim(rtrim(number_format($abs / 1e5, 2), '0'), '.') . ' L',
            $abs >= 1e3 => rtrim(rtrim(number_format($abs / 1e3, 1), '0'), '.') . 'K',
            default => number_format($abs, 0),
        };
    }

    // Full rupee figure with Indian digit grouping (12,34,567).
    public static function inrFull(float $v): string
    {
        $int = (string) (int) round(abs($v));
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $grouped = $rest !== '' ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last3 : $last3;

        return ($v < 0 ? '-' : '') . '₹' . $grouped;
    }
}
