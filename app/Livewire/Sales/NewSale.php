<?php
namespace App\Livewire\Sales;

use App\Models\Customer\Customer;
use App\Models\Location;
use App\Models\Orders\Order;
use App\Models\Sales\Sale;
use App\Models\Sales\SalePayment;
use App\Models\Stock\Item;
use App\Services\PricingService;
use App\Support\Phone;
use App\Support\StockLookup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * New Sale as a step form (8 Oct change list, section 12): Customer, Items, Charges, Payment,
 * Review. Going back to an earlier step is always possible and the figures follow.
 *
 * On save the sale is held as 'reserved' until an admin verifies it (rule 10). The bill is paid
 * in parts if need be; the balance is worked out from the payments and cleared later.
 */
class NewSale extends Component
{
    public const STEPS = [1 => 'Customer', 2 => 'Items', 3 => 'Charges', 4 => 'Payment', 5 => 'Review'];

    public int $step = 1;
    public int $reach = 1;

    // The order this sale delivers, when the sale is started from an order's path.
    #[Url(as: 'order', except: '')]
    public ?int $orderId = null;

    // Set when this sale is how a matured monthly scheme ends (8 Oct change list, 16.1).
    #[Url(as: 'scheme', except: '')]
    public ?int $schemeId = null;


    // ---- 1. customer
    public string $customerSearch = '';
    #[Url(as: 'customer', except: '')]
    public ?int $customerId = null;
    public bool $addingCustomer = false;
    public string $newName = '';
    public string $newPhone = '';
    public string $referralCode = '';
    public ?int $referralCustomerId = null;

    // ---- 2. items: item_id => ['label', 'category', 'price', 'inVault', 'order']
    public string $itemSearch = '';
    public array $cart = [];
    public string $overrideNote = '';

    // ---- 3. charges
    public array $extras = [];            // [['name' => ..., 'amount' => ...]]
    public string $adjustType = 'flat';   // flat | percent
    public $adjustValue = '';

    // ---- 4. payment
    public array $payments = [['mode' => 'cash', 'amount' => '']];

    public string $notes = '';

    public function mount(): void
    {
        if ($this->schemeId) {
            $scheme = \App\Models\Customer\InstallmentScheme::where('status', 'active')->find($this->schemeId);
            if ($scheme) {
                $this->customerId = $scheme->customer_id;
                $this->payments = [['mode' => 'cash', 'amount' => (string) $scheme->paid_in, 'note' => 'Paid in through the monthly scheme']];
            } else {
                $this->schemeId = null;
            }
        }
        if ($this->customerId && ! Customer::whereKey($this->customerId)->exists()) {
            $this->customerId = null;
        }
        if ($this->customerId) {
            $this->step = max($this->step, 2);
            $this->reach = max($this->reach, 2);
        }

        if (! $this->orderId) {
            return;
        }
        $order = Order::with('customer', 'stockItem')->whereIn('status', ['placed', 'confirmed', 'ready'])->find($this->orderId);
        if (! $order) {
            $this->orderId = null;

            return;
        }
        $this->customerId = $order->customer_id;
        $this->reach = 2;
        $this->step = 2;
        // The advance already paid on the order counts towards this bill. Staff set the mode it was paid in.
        if ((float) $order->advance_amount > 0) {
            $this->payments = [['mode' => 'cash', 'amount' => (string) (float) $order->advance_amount, 'note' => "Advance paid on order #{$order->id}"]];
        }
        if ($order->stockItem && $order->stockItem->status === 'in_stock') {
            $this->cart[$order->stockItem->id] = $this->cartLine($order->stockItem);
        }
    }

    // ================================================================ steps

    public function goToStep(int $n): void
    {
        if ($n >= 1 && $n <= 5 && $n <= $this->reach) {
            $this->resetValidation();
            $this->step = $n;
        }
    }

    public function next(): void
    {
        if (! $this->stepIsValid($this->step)) {
            return;
        }
        $this->step = min(5, $this->step + 1);
        $this->reach = max($this->reach, $this->step);
        $this->refreshCart();
    }

    private function stepIsValid(int $step): bool
    {
        $this->resetValidation();

        if ($step === 1 && ! $this->customerId) {
            $this->addError('customerId', 'Choose the customer, or add a new one.');

            return false;
        }
        if ($step === 2) {
            if (! $this->cart) {
                $this->addError('cart', 'Add at least one piece.');

                return false;
            }
            $this->refreshCart();
            if (collect($this->cart)->contains('inVault', true)) {
                $this->addError('cart', 'Some pieces are still recorded as in the vault. Move them to the counter first.');

                return false;
            }
            if (collect($this->cart)->contains(fn ($c) => $c['order'] !== null) && ! $this->canOverride()) {
                $this->addError('cart', 'A piece is held for a customer order. An admin has to approve selling it.');

                return false;
            }
            if (collect($this->cart)->contains(fn ($c) => $c['order'] !== null) && trim($this->overrideNote) === '') {
                $this->addError('overrideNote', 'Say why a piece held for an order is being sold.');

                return false;
            }
        }
        if ($step === 3) {
            foreach ($this->extras as $i => $e) {
                if (($e['name'] ?? '') === '' && ($e['amount'] ?? '') === '') {
                    continue;
                }
                if (($e['name'] ?? '') === '' || ! is_numeric($e['amount'] ?? null) || (float) $e['amount'] <= 0) {
                    $this->addError("extras.{$i}.amount", 'Give each extra charge a name and an amount.');

                    return false;
                }
            }
            if ($this->adjustValue !== '' && (! is_numeric($this->adjustValue) || (float) $this->adjustValue < 0 || ($this->adjustType === 'percent' && (float) $this->adjustValue > 100))) {
                $this->addError('adjustValue', $this->adjustType === 'percent' ? 'Enter a percentage between 0 and 100.' : 'Enter an amount.');

                return false;
            }
        }
        if ($step === 4) {
            foreach ($this->payments as $i => $p) {
                if (($p['amount'] ?? '') !== '' && (! is_numeric($p['amount']) || (float) $p['amount'] < 0)) {
                    $this->addError("payments.{$i}.amount", 'Enter an amount.');

                    return false;
                }
            }
            if ($this->paidTotal > $this->total + 0.005) {
                $this->addError('payments', 'The payments add up to more than the bill.');

                return false;
            }
        }

        return true;
    }

    // ================================================================ 1. customer

    public function chooseCustomer(int $id): void
    {
        $this->customerId = $id;
        $this->customerSearch = '';
        $this->addingCustomer = false;
        $this->resetErrorBag('customerId');
        $this->checkReferral();
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
        $this->referralCustomerId = null;
    }

    public function saveNewCustomer(): void
    {
        $this->newPhone = Phone::normalize($this->newPhone);
        $this->validate([
            'newName' => ['required', 'string', 'max:100'],
            'newPhone' => ['required', 'digits:10'],
        ], ['newPhone.digits' => 'Enter a 10 digit mobile number.', 'newName.required' => 'Enter the customer\'s name.'], ['newName' => 'name', 'newPhone' => 'phone']);

        $existing = Customer::where('phone', $this->newPhone)->first();
        if ($existing) {
            $this->chooseCustomer($existing->id);
            $this->dispatch('toast', message: "{$existing->name} already has this number, so I chose them.", type: 'info');
            $this->reset(['newName', 'newPhone']);

            return;
        }

        $c = Customer::create(['name' => $this->newName, 'phone' => $this->newPhone, 'status' => 'past_customer']);
        $this->reset(['newName', 'newPhone']);
        $this->chooseCustomer($c->id);
        $this->dispatch('toast', message: "{$c->name} added.", type: 'success');
    }

    // A person can not use their own code on their own purchase.
    public function checkReferral(): void
    {
        $this->resetErrorBag('referralCode');
        $this->referralCustomerId = null;
        $code = strtoupper(trim($this->referralCode));
        if ($code === '') {
            return;
        }
        $owner = Customer::where('referral_code', $code)->first();
        if (! $owner) {
            $this->addError('referralCode', 'No customer has this referral code.');

            return;
        }
        if ($owner->id === $this->customerId) {
            $this->addError('referralCode', 'A customer can not use their own referral code on their own purchase.');

            return;
        }
        $this->referralCustomerId = $owner->id;
    }

    public function updatedReferralCode(): void
    {
        $this->checkReferral();
    }

    // ================================================================ 2. items

    public function addByCode(string $raw): void
    {
        $item = StockLookup::item($raw);
        if (! $item) {
            $this->itemSearch = trim($raw);

            return;
        }
        $this->addItem($item->id);
    }

    public function addItem(int $itemId): void
    {
        $item = Item::find($itemId);
        if (! $item) {
            return;
        }
        $this->itemSearch = '';
        if (isset($this->cart[$itemId])) {
            return;
        }
        if ($item->status !== 'in_stock') {
            $this->dispatch('toast', message: "{$item->label} is " . str_replace('_', ' ', $item->status) . ' and can not be billed.', type: 'error');

            return;
        }

        $this->cart[$itemId] = $this->cartLine($item);
        $this->resetErrorBag('cart');
    }

    private function cartLine(Item $item): array
    {
        $order = Order::openForItem($item->id);
        if ($order && $this->orderId && $order->id === $this->orderId) {
            $order = null; // selling it to the customer it is held for
        }

        return [
            'label' => $item->label,
            'category' => $item->category,
            'weight' => (float) $item->weight,
            'price' => app(PricingService::class)->priceFor($item),
            'inVault' => Location::isInVault($item),
            'order' => $order ? "Order #{$order->id} for " . ($order->customer?->name ?? 'a customer') : null,
        ];
    }

    // Prices and the vault check are read again whenever the screen moves on.
    private function refreshCart(): void
    {
        foreach (Item::whereKey(array_keys($this->cart))->get() as $item) {
            $this->cart[$item->id] = $this->cartLine($item);
        }
    }

    public function removeItem(int $itemId): void
    {
        unset($this->cart[$itemId]);
    }

    // The offer made when a piece is still recorded as in the vault: move it out to the counter first.
    public function moveToCounter(int $itemId): void
    {
        $item = Item::findOrFail($itemId);
        abort_unless(Auth::user()?->can('movement.create'), 403);
        if (Location::isInVault($item)) {
            Location::sendToFloor($item);
        }
        $this->cart[$itemId] = $this->cartLine($item);
        $this->resetErrorBag('cart');
        $this->dispatch('toast', message: "{$item->label} moved to the counter.", type: 'success');
    }

    public function canOverride(): bool
    {
        return (bool) Auth::user()?->can('sale.approve');
    }

    // ================================================================ 3 and 4

    public function addExtra(): void
    {
        $this->extras[] = ['name' => '', 'amount' => ''];
    }

    public function removeExtra(int $i): void
    {
        unset($this->extras[$i]);
        $this->extras = array_values($this->extras);
    }

    public function addPayment(): void
    {
        $this->payments[] = ['mode' => 'cash', 'amount' => ''];
    }

    public function removePayment(int $i): void
    {
        unset($this->payments[$i]);
        $this->payments = array_values($this->payments) ?: [['mode' => 'cash', 'amount' => '']];
    }

    // One tap to put what is still due on a payment line.
    public function fillBalance(int $i): void
    {
        $others = collect($this->payments)->except($i)->sum(fn ($p) => is_numeric($p['amount'] ?? null) ? (float) $p['amount'] : 0);
        $this->payments[$i]['amount'] = (string) max(0, round($this->total - $others, 2));
    }

    // ================================================================ figures

    public function getSubtotalProperty(): float
    {
        return round(collect($this->cart)->sum('price'), 2);
    }

    public function getExtrasTotalProperty(): float
    {
        return round(collect($this->extras)->sum(fn ($e) => is_numeric($e['amount'] ?? null) && ($e['name'] ?? '') !== '' ? (float) $e['amount'] : 0), 2);
    }

    // The adjustment is a flat amount or a percentage of the bill. It needs no approval.
    public function getAdjustmentProperty(): float
    {
        $base = $this->subtotal + $this->extrasTotal;
        $v = is_numeric($this->adjustValue) ? (float) $this->adjustValue : 0.0;
        $amount = $this->adjustType === 'percent' ? $base * min(100, $v) / 100 : $v;

        return round(min($base, max(0, $amount)), 2);
    }

    public function getTotalProperty(): float
    {
        return round($this->subtotal + $this->extrasTotal - $this->adjustment, 2);
    }

    public function getPaidTotalProperty(): float
    {
        return round(collect($this->payments)->sum(fn ($p) => is_numeric($p['amount'] ?? null) ? (float) $p['amount'] : 0), 2);
    }

    public function getBalanceProperty(): float
    {
        return round($this->total - $this->paidTotal, 2);
    }

    public function getCustomerObjectProperty()
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    // ================================================================ save

    public function submit()
    {
        abort_unless(Auth::user()?->can('sale.create'), 403);

        foreach ([1, 2, 3, 4] as $s) {
            if (! $this->stepIsValid($s)) {
                $this->step = $s;

                return;
            }
        }

        // Pieces may have been taken meanwhile: only pieces still in stock can be held for a sale.
        $items = Item::whereKey(array_keys($this->cart))->where('status', 'in_stock')->get();
        if ($items->count() !== count($this->cart)) {
            $this->step = 2;
            $this->addError('cart', 'A piece on this bill is no longer in stock. Remove it and try again.');
            $this->refreshCart();

            return;
        }

        $pricing = app(PricingService::class);
        $extras = collect($this->extras)->filter(fn ($e) => ($e['name'] ?? '') !== '' && is_numeric($e['amount'] ?? null))
            ->map(fn ($e) => ['name' => $e['name'], 'amount' => round((float) $e['amount'], 2)])->values()->all();

        $sale = DB::transaction(function () use ($items, $pricing, $extras) {
            $prices = $items->mapWithKeys(fn ($i) => [$i->id => $pricing->priceFor($i)]);
            $subtotal = round($prices->sum(), 2);
            $base = $subtotal + round(array_sum(array_column($extras, 'amount')), 2);
            $v = is_numeric($this->adjustValue) ? (float) $this->adjustValue : 0.0;
            $adjustment = round(min($base, max(0, $this->adjustType === 'percent' ? $base * min(100, $v) / 100 : $v)), 2);

            $sale = Sale::create([
                'customer_id' => $this->customerId,
                'referral_customer_id' => $this->referralCustomerId,
                'invoice_number' => 'RESV-' . now()->format('YmdHis') . '-' . $this->customerId, // the Tally bill number replaces it at verification
                'type' => 'sale',
                'cgst' => 0, 'sgst' => 0, 'igst' => 0,
                'additional_charges' => $extras ?: null,
                'discount' => $adjustment,
                'adjustment_type' => $adjustment > 0 ? $this->adjustType : null,
                'adjustment_value' => $adjustment > 0 ? $v : null,
                'accountant_note' => trim($this->notes) ?: null,
                'order_override_note' => collect($this->cart)->contains(fn ($c) => $c['order'] !== null) ? trim($this->overrideNote) : null,
                'total' => round($base - $adjustment, 2),
                'confirmed_by_accountant' => false,
                'created_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                $sale->items()->attach($item->id, ['price_at_sale' => $prices[$item->id]]);
                $item->update(['status' => 'reserved']); // per item, so each piece's history shows it
            }

            foreach ($this->payments as $p) {
                if (is_numeric($p['amount'] ?? null) && (float) $p['amount'] > 0) {
                    SalePayment::create(['sale_id' => $sale->id, 'mode' => $p['mode'], 'amount' => round((float) $p['amount'], 2), 'note' => $p['note'] ?? null, 'user_id' => Auth::id()]);
                }
            }

            if ($this->schemeId && ($scheme = \App\Models\Customer\InstallmentScheme::with('customer')->where('status', 'active')->find($this->schemeId))) {
                \App\Livewire\Installments\SchemeList::complete($scheme, 'sale', $sale->id);
            }

            // Link the order: it is delivered once an admin verifies this sale.
            if ($this->orderId && ($order = Order::find($this->orderId))) {
                $order->update(['converted_sale_id' => $sale->id]);
            }

            return $sale;
        });

        session()->flash('toast', "Sale #{$sale->id} is held until an admin verifies it.");

        return $this->redirectRoute('sales.invoice', $sale);
    }

    public function render()
    {
        return view('livewire.sales.new-sale', [
            'steps' => self::STEPS,
            'customer' => $this->customerObject,
            'referrer' => $this->referralCustomerId ? Customer::find($this->referralCustomerId) : null,
            'customerResults' => $this->customerSearch !== ''
                ? Customer::where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "%{$this->customerSearch}%")->limit(8)->get()
                : collect(),
            'itemResults' => $this->itemSearch !== ''
                ? Item::where('status', 'in_stock')->whereNotIn('id', array_keys($this->cart))->searchAnything($this->itemSearch)->limit(8)->get()
                : collect(),
            'modes' => SalePayment::MODES,
            'canOverride' => $this->canOverride(),
            'hasOrderWarning' => collect($this->cart)->contains(fn ($c) => $c['order'] !== null),
        ])->layout('components.layouts.app', ['title' => 'New Sale — Radharani Jewellery']);
    }
}
