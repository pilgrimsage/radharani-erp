<?php
namespace App\Livewire\Referral;

use App\Models\Customer\Customer;
use App\Models\Customer\ReferralPoint;
use App\Models\Customer\ReferralSetting;
use App\Models\Sales\Sale;
use App\Models\Stock\Item;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Referral (8 Oct change list, section 15; it replaces Loyalty). Codes are opt-in: only customers
 * who choose to take part get one. For each code the screen shows the grams of metal bought against
 * it. The owner sets how points are worked out; staff award them from the sales waiting for points.
 * The exact award mechanism is still to be designed, so awarding is a deliberate click, never automatic.
 */
class ReferralDesk extends Component
{
    #[Url(except: 'codes')]
    public string $tab = 'codes'; // codes | waiting | points | rules

    public string $customerSearch = '';
    public string $search = '';

    // manual award
    public ?int $awardCustomerId = null;
    public $awardPoints = '';
    public string $awardNote = '';

    /** @var array<int, string> sale_id => points to award, as typed */
    public array $waitingPoints = [];

    // rules
    public $pointsPerGram = '';
    public $firstSaleBonus = '';

    public function mount(): void
    {
        $s = ReferralSetting::current();
        $this->pointsPerGram = (string) (float) $s->points_per_gram;
        $this->firstSaleBonus = (string) $s->first_sale_bonus;
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['codes', 'waiting', 'points', 'rules'], true)) {
            $this->tab = $tab;
            $this->resetValidation();
        }
    }

    // ---------------------------------------------------------------- codes

    private function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= Item::CODE_ALPHABET[random_int(0, strlen(Item::CODE_ALPHABET) - 1)];
            }
        } while (Customer::where('referral_code', $code)->exists());

        return $code;
    }

    public function optIn(int $customerId): void
    {
        $c = Customer::findOrFail($customerId);
        if (! $c->referral_code) {
            $c->update(['referral_code' => $this->newCode(), 'referral_opted_at' => now()]);
        }
        $this->customerSearch = '';
        $this->dispatch('toast', message: "{$c->name} now has the code {$c->referral_code}.", type: 'success');
    }

    public function withdraw(int $customerId): void
    {
        $c = Customer::findOrFail($customerId);
        $c->update(['referral_code' => null, 'referral_opted_at' => null]);
        $this->dispatch('toast', message: "{$c->name} is no longer in the referral programme. Past sales keep their record.", type: 'success');
    }

    // Grams of metal (net weight where known) in the verified sales made against each referrer's code.
    private function gramsByReferrer(): array
    {
        return DB::table('sales')->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')->join('items', 'items.id', '=', 'sale_items.item_id')
            ->where('sales.confirmed_by_accountant', true)->whereNotNull('sales.referral_customer_id')
            ->groupBy('sales.referral_customer_id')
            ->selectRaw('sales.referral_customer_id as id, SUM(COALESCE(items.net_weight, items.weight)) as grams, COUNT(DISTINCT sales.id) as sales, COUNT(DISTINCT sales.customer_id) as customers')
            ->get()->keyBy('id')->all();
    }

    // ---------------------------------------------------------------- points

    /** Verified referred sales that have no points yet, with what the rules would give. */
    private function waiting()
    {
        $rule = ReferralSetting::current();
        $awarded = ReferralPoint::whereNotNull('sale_id')->pluck('sale_id')->all();

        return Sale::with(['customer:id,name', 'referrer:id,name', 'items'])
            ->where('confirmed_by_accountant', true)->whereNotNull('referral_customer_id')->whereNotIn('id', $awarded ?: [0])
            ->orderBy('id')->get()->map(function (Sale $sale) use ($rule) {
                $grams = round($sale->items->sum(fn ($i) => (float) ($i->net_weight ?: $i->weight)), 3);
                $first = ! Sale::where('customer_id', $sale->customer_id)->where('confirmed_by_accountant', true)->where('id', '<', $sale->id)->exists();
                $suggested = (int) round($grams * (float) $rule->points_per_gram) + ($first ? (int) $rule->first_sale_bonus : 0);

                return ['sale' => $sale, 'grams' => $grams, 'first' => $first, 'suggested' => $suggested];
            });
    }

    public function award(int $saleId): void
    {
        $sale = Sale::whereNotNull('referral_customer_id')->where('confirmed_by_accountant', true)->findOrFail($saleId);
        if (ReferralPoint::where('sale_id', $saleId)->exists()) {
            return;
        }
        $points = $this->waitingPoints[$saleId] ?? null;
        if (! is_numeric($points)) {
            $row = $this->waiting()->firstWhere(fn ($w) => $w['sale']->id === $saleId);
            $points = $row['suggested'] ?? 0;
        }
        if ((int) $points < 0) {
            $this->addError("waitingPoints.{$saleId}", 'Points can not be negative.');

            return;
        }

        ReferralPoint::create(['customer_id' => $sale->referral_customer_id, 'points' => (int) $points, 'sale_id' => $saleId, 'note' => "Sale #{$saleId}", 'user_id' => Auth::id()]);
        $this->dispatch('toast', message: (int) $points . ' points awarded to ' . ($sale->referrer?->name ?? 'the referrer') . '.', type: 'success');
    }

    public function awardManual(): void
    {
        $this->validate(['awardCustomerId' => 'required|exists:customers,id', 'awardPoints' => 'required|integer|not_in:0', 'awardNote' => 'nullable|string|max:255'],
            ['awardCustomerId.required' => 'Choose the customer.', 'awardPoints.required' => 'Enter the points.'], ['awardPoints' => 'points']);
        ReferralPoint::create(['customer_id' => $this->awardCustomerId, 'points' => (int) $this->awardPoints, 'note' => $this->awardNote ?: 'Manual entry', 'user_id' => Auth::id()]);
        $this->reset(['awardPoints', 'awardNote']);
        $this->dispatch('toast', message: 'Points recorded.', type: 'success');
    }

    public function saveRules(): void
    {
        abort_unless(Auth::user()?->can('role.manage'), 403, 'Only the owner sets the rules.');
        $this->validate(['pointsPerGram' => 'required|numeric|min:0|max:10000', 'firstSaleBonus' => 'required|integer|min:0|max:1000000']);
        ReferralSetting::current()->update(['points_per_gram' => $this->pointsPerGram, 'first_sale_bonus' => $this->firstSaleBonus, 'updated_by' => Auth::id()]);
        $this->dispatch('toast', message: 'Rules saved.', type: 'success');
    }

    public function render()
    {
        $stats = $this->tab === 'codes' ? $this->gramsByReferrer() : [];

        return view('livewire.referral.referral-desk', [
            'codes' => $this->tab === 'codes'
                ? Customer::whereNotNull('referral_code')->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('referral_code', 'like', "%{$this->search}%"))->orderBy('name')->get()
                : collect(),
            'stats' => $stats,
            'customerResults' => $this->customerSearch !== '' ? Customer::whereNull('referral_code')->where(fn ($q) => $q->where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "%{$this->customerSearch}%"))->limit(8)->get() : collect(),
            'waiting' => $this->tab === 'waiting' ? $this->waiting() : collect(),
            'ledger' => $this->tab === 'points' ? ReferralPoint::with('customer:id,name', 'user:id,name')->latest('id')->limit(100)->get() : collect(),
            'totals' => $this->tab === 'points' ? ReferralPoint::selectRaw('customer_id, SUM(points) as total')->groupBy('customer_id')->pluck('total', 'customer_id') : collect(),
            'members' => $this->tab === 'points' ? Customer::whereNotNull('referral_code')->orderBy('name')->get(['id', 'name']) : collect(),
            'canSetRules' => (bool) Auth::user()?->can('role.manage'),
        ])->layout('components.layouts.app', ['title' => 'Referral — Radharani Jewellery']);
    }
}
