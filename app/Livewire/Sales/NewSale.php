<?php
namespace App\Livewire\Sales;

use App\Models\Customer\Customer;
use App\Models\Location;
use App\Models\Orders\Order;
use App\Models\Sales\Sale;
use App\Models\Sales\SalePayment;
use App\Models\Stock\Item;
use App\Models\Exchange\ExchangeTransaction;
use App\Services\AdvanceService;
use App\Services\ExchangeCredit;
use App\Services\InsufficientAdvance;
use App\Services\InsufficientExchangeCredit;
use App\Services\PricingService;
use App\Support\Phone;
use App\Support\StockLookup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * New Sale as a step form (8 Oct change list, section 12; order changed 28 Oct): Items, This bill, Customer,
 * Payment, Review. Going back to an earlier step is always possible and the figures follow.
 *
 * On save the sale is held as 'reserved' until an admin verifies it (rule 10). The bill is paid
 * in parts if need be; the balance is worked out from the payments and cleared later.
 */
class NewSale extends Component
{
    // 28 Oct: the pieces first, then the whole bill on one screen, then who is buying (the shared Walk-in customer is one tap), then payment.
    public const STEPS = [1 => 'Items', 2 => 'This bill', 3 => 'Customer', 4 => 'Payment', 5 => 'Review'];

    public int $step = 1;
    public int $reach = 1;

    // The order this sale delivers, when the sale is started from an order's path.
    #[Url(as: 'order', except: '')]
    public ?int $orderId = null;

    // A settled exchange whose credit this sale is paid with (started from the exchange's "Use in a sale").
    #[Url(as: 'exchange', except: '')]
    public ?int $exchangeId = null;

    // Set when this sale is how a matured monthly scheme ends (8 Oct change list, 16.1).
    #[Url(as: 'scheme', except: '')]
    public ?int $schemeId = null;


    // A piece to put on the bill from the start: "Add sale" on a piece's own page.
    #[Url(as: 'item', except: '')]
    public ?int $startItemId = null;

    // ---- 1. customer
    public string $customerSearch = '';
    #[Url(as: 'customer', except: '')]
    public ?int $customerId = null;
    public string $referralCode = '';
    public ?int $referralCustomerId = null;

    // ---- 2. items: item_id => ['label', 'category', 'price', 'inVault', 'order']
    public string $itemSearch = '';
    public array $cart = [];
    public string $overrideNote = '';

    // Pieces still recorded as in the vault: a popup offers to move them to the counter (nothing moves until it is pressed).
    public bool $showVaultMove = false;
    public array $vaultPieces = [];

    // GST on this bill: the owner's defaults, which staff can change (or set to 0) before billing.
    // The owner's GST rates (Pricing rules > GST), set when the form opens. No longer a choice on the bill (17 Oct); locked so the browser can not change them.
    #[\Livewire\Attributes\Locked]
    public string $gstJewellery = '';
    #[\Livewire\Attributes\Locked]
    public string $gstHallmark = '';

    // ---- 3. charges
    public array $extras = [];            // [['name' => ..., 'amount' => ...]]
    // The bargain discount at the very end (after GST and the round-off, as Tally's "Discount Sales"): an amount, or the final price to pay.
    public string $finalType = 'flat';    // flat | final
    public $finalValue = '';

    // ---- 4. payment
    public array $payments = [['mode' => 'cash', 'amount' => '']];

    // The payment line filled in for the balance due when step 4 opens, and what it was filled with,
    // so it follows the bill if staff go back and change it but never overwrites an amount they typed.
    public ?int $autoPaymentIndex = null;
    public ?string $autoPaymentAmount = null;

    public string $notes = '';

    public function mount(): void
    {
        [$jewellery, $hallmark] = app(PricingService::class)->gstRates();
        $this->gstJewellery = (string) $jewellery;
        $this->gstHallmark = (string) $hallmark;

        if ($this->schemeId) {
            $scheme = \App\Models\Customer\InstallmentScheme::where('status', 'active')->find($this->schemeId);
            if ($scheme) {
                $this->customerId = $scheme->customer_id;
                $this->payments = [['mode' => 'cash', 'amount' => (string) $scheme->paid_in, 'note' => 'Paid in through the monthly scheme']];
            } else {
                $this->schemeId = null;
            }
        }
        if ($this->exchangeId) {
            $exchange = ExchangeTransaction::find($this->exchangeId);
            if ($exchange) {
                // Started from the exchange summary: the customer comes over even before there is any credit to spend.
                $this->customerId = $exchange->customer_id;
            }
            if (! $exchange || $exchange->creditRemaining() <= 0) {
                $this->exchangeId = null;
            }
        }
        if ($this->customerId && ! Customer::whereKey($this->customerId)->exists()) {
            $this->customerId = null;
        }
        if ($this->startItemId) {
            $piece = Item::find($this->startItemId);
            $this->startItemId = null; // only starts the bill: the piece can be taken off like any other
            if ($piece && $piece->status === 'in_stock') {
                $this->cart[$piece->id] = $this->cartLine($piece);
                $this->addDefaultCharges($piece);
            } elseif ($piece) {
                $this->dispatch('toast', message: "{$piece->label} is " . str_replace('_', ' ', $piece->status) . ' and can not be billed.', type: 'error');
            }
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
        // The advance already paid on the order counts towards this bill. Staff set the mode it was paid in.
        $lines = [];
        if ((float) $order->advance_amount > 0) {
            $fromExchange = app(ExchangeCredit::class)->orderCredit($order->id);
            $lines[] = ['mode' => 'cash', 'amount' => (string) (float) $order->advance_amount,
                'note' => "Advance paid on order #{$order->id}" . ($fromExchange > 0 ? ' (₹' . number_format($fromExchange) . ' of it from exchange credit)' : '')];
        }
        // Advances received after the order was placed keep the mode they came in.
        foreach ($order->payments()->orderBy('id')->get() as $p) {
            $lines[] = ['mode' => $p->mode, 'amount' => (string) (float) $p->amount, 'note' => "Advance on order #{$order->id}, " . $p->created_at->format('j M') . ($p->note ? ": {$p->note}" : '')];
        }
        if ($lines) {
            $this->payments = $lines;
        }
        if ($order->stockItem && $order->stockItem->status === 'in_stock') {
            $this->cart[$order->stockItem->id] = $this->cartLine($order->stockItem);
            $this->addDefaultCharges($order->stockItem);
        }

        // Pieces made for this order (karigar or hallmarking) that an admin has confirmed into stock.
        Item::where('status', 'in_stock')->where(function ($q) use ($order) {
            $q->whereIn('source_karigar_batch_id', $order->karigarBatches()->select('id'))
                ->orWhereIn('source_hallmark_batch_id', $order->hallmarkBatches()->select('id'));
        })->get()->each(function ($piece) {
            if (! isset($this->cart[$piece->id])) {
                $this->cart[$piece->id] = $this->cartLine($piece);
                $this->addDefaultCharges($piece);
            }
        });
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
        if ($this->step === 4) {
            $this->prefillPayment();
        }
    }

    // Opens the payment step with the balance due already on a payment line. Staff change it for a part payment.
    // These read the figures through their getters, not as $this->total / $this->balance: Livewire keeps an accessor's
    // value for the rest of the request, so reading one here and then changing the payments would leave the page stale.
    private function prefillPayment(): void
    {
        $this->prefillExchangeCredit();

        $i = $this->autoPaymentIndex;
        $untouched = $i !== null && isset($this->payments[$i]) && (string) ($this->payments[$i]['amount'] ?? '') === (string) $this->autoPaymentAmount;

        if (! $untouched) {
            $i = collect($this->payments)->search(fn ($p) => (string) ($p['amount'] ?? '') === '');
            if ($i === false) {
                if ($this->getBalanceProperty() <= 0) {
                    return;
                }
                $this->payments[] = ['mode' => 'cash', 'amount' => ''];
                $i = array_key_last($this->payments);
            }
        }

        $this->fillBalance($i);
        if ((float) $this->payments[$i]['amount'] <= 0) {
            $this->payments[$i]['amount'] = '';
        }
        $this->autoPaymentIndex = $i;
        $this->autoPaymentAmount = (string) $this->payments[$i]['amount'];
    }

    private function stepIsValid(int $step): bool
    {
        $this->resetValidation();

        if ($step === 3 && ! $this->customerId) {
            $this->addError('customerId', 'Choose the customer, the walk-in customer, or add a new one.');

            return false;
        }
        if ($step === 1) {
            if (! $this->cart) {
                $this->addError('cart', 'Add at least one piece.');

                return false;
            }
            foreach ($this->cart as $id => $c) {
                $w = trim((string) ($c['weight_input'] ?? ''));
                if ($w !== '' && (! is_numeric($w) || (float) $w <= 0)) {
                    $this->addError("cart.{$id}.weight_input", 'Enter a weight above 0, or leave it as the piece weight.');

                    return false;
                }
            }
            $this->refreshCart();
            if (collect($this->cart)->contains('inVault', true)) {
                $this->openVaultPrompt();

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
        if ($step === 2) {
            foreach ($this->extras as $i => $e) {
                if (($e['name'] ?? '') === '' && ($e['amount'] ?? '') === '') {
                    continue;
                }
                if (($e['name'] ?? '') === '' || ! is_numeric($e['amount'] ?? null) || (float) $e['amount'] <= 0) {
                    $this->addError("extras.{$i}.amount", 'Give each extra charge a name and an amount.');

                    return false;
                }
            }
        }
        if ($step === 2 && trim((string) $this->finalValue) !== '') {
            $bill = $this->totals();
            if (! is_numeric($this->finalValue) || (float) $this->finalValue < 0) {
                $this->addError('finalValue', 'Enter an amount.');

                return false;
            }
            if ($bill['final_discount_wanted'] > $bill['final_discount_cap'] + 0.005) {
                $this->addError('finalValue', 'The discount can not be more than ₹' . number_format($bill['final_discount_cap'], 2) . ', the making charge left on this bill with its GST. The metal is never discounted.');

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
            foreach ($this->payments as $i => $p) {
                if (! array_key_exists($p['mode'] ?? '', SalePayment::MODES)) {
                    $this->addError("payments.{$i}.amount", 'Choose cash or bank.');

                    return false;
                }
                $amount = is_numeric($p['amount'] ?? null) ? (float) $p['amount'] : 0.0;
                if (($p['mode'] ?? '') === 'advance' && $amount > $this->advanceLeft($i) + 0.005) {
                    $this->addError("payments.{$i}.amount", 'That is more advance than this customer has left (₹' . number_format($this->advanceLeft($i), 2) . ').');

                    return false;
                }
                if (($p['mode'] ?? '') === 'exchange' && $amount > 0) {
                    $exchangeId = (int) ($p['exchange_id'] ?? 0);
                    if (! $exchangeId || $amount > $this->exchangeLeft($exchangeId, $i) + 0.005) {
                        $this->addError("payments.{$i}.amount", $exchangeId ? 'That is more exchange credit than this customer has left (₹' . number_format($this->exchangeLeft($exchangeId, $i), 2) . ').' : 'Exchange credit can only be used from the exchange panel.');

                        return false;
                    }
                }
            }
            if ($this->getPaidTotalProperty() > $this->getTotalProperty() + 0.005) {
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
        $this->resetErrorBag('customerId');
        // The walk-in customer has no code to refer anyone with.
        if (Customer::whereKey($id)->value('is_walk_in')) {
            $this->referralCode = '';
        }
        $this->checkReferral();
    }

    // One tap for a buyer who gives no details: the shared Walk-in customer, with its own ledger.
    public function chooseWalkIn(): void
    {
        $this->chooseCustomer(Customer::walkIn()->id);
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
        $this->referralCustomerId = null;
    }

    // The shared customer form saved (or found) someone: they become the customer of this sale.
    #[On('customer-saved')]
    public function customerSaved(int $id): void
    {
        $this->chooseCustomer($id);
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
        $this->addDefaultCharges($item);
        $this->resetErrorBag('cart');
        if ($this->cart[$itemId]['inVault']) {
            $this->openVaultPrompt();
        }
    }

    // The popup that offers to move what is still in the vault to the counter. Nothing moves until Move to Counter is pressed.
    private function openVaultPrompt(): void
    {
        $this->vaultPieces = collect($this->cart)->filter(fn ($c) => $c['inVault'])->map(fn ($c, $id) => ['id' => $id, 'label' => $c['label'], 'category' => $c['category']])->values()->all();
        $this->showVaultMove = ! empty($this->vaultPieces);
    }

    public function moveVaultPiecesToCounter(): void
    {
        abort_unless(Auth::user()?->can('movement.create'), 403);
        foreach (Item::whereKey(collect($this->vaultPieces)->pluck('id'))->get() as $item) {
            if (Location::isInVault($item)) {
                Location::sendToFloor($item);
            }
            $this->cart[$item->id] = $this->cartLine($item, $this->cart[$item->id] ?? []);
        }
        $n = count($this->vaultPieces);
        $this->showVaultMove = false;
        $this->vaultPieces = [];
        $this->resetErrorBag('cart');
        $this->dispatch('toast', message: $n . ' ' . \Illuminate\Support\Str::plural('piece', $n) . ' moved to the counter.', type: 'success');
    }

    // The additional charges a piece carries by default start on the bill, tied to the piece. Staff can change or remove them on the charges step.
    private function addDefaultCharges(Item $item): void
    {
        foreach ($item->defaultCharges() as $charge) {
            $this->extras[] = ['name' => $charge['name'], 'amount' => (string) $charge['amount'], 'item_id' => $item->id];
        }
    }

    /**
     * One piece on the bill. $input is what staff changed on this bill (never the piece): the weight to price it at,
     * a note for that, and a making charge bargained as a rate or an amount. Everything else is worked out again here.
     */
    private function cartLine(Item $item, array $input = []): array
    {
        $order = Order::openForItem($item->id);
        if ($order && $this->orderId && $order->id === $this->orderId) {
            $order = null; // selling it to the customer it is held for
        }

        $weightInput = trim((string) ($input['weight_input'] ?? ''));
        $set = is_array($input['making_set'] ?? null) ? $input['making_set'] : null;

        $pricing = app(PricingService::class);
        $b = $pricing->breakdown($item, [
            'weight' => is_numeric($weightInput) && (float) $weightInput > 0 ? (float) $weightInput : null,
            'making_rate' => $set['rate'] ?? null,
            'making_amount' => $set['amount'] ?? null,
        ]);
        $bargained = abs((float) $b['making_rate'] - (float) $b['making_default_rate']) > 0.004;

        return [
            'label' => $item->label,
            'category' => $item->category,
            'weight' => $b['weight'],                 // what the price is worked on
            'weight_listed' => $b['weight_listed'],   // what the piece's record says
            'weight_input' => $weightInput,
            'weight_note' => (string) ($input['weight_note'] ?? ''),
            'metal_value' => $b['metal_value'],
            'making_kind' => $b['making_kind'],
            'making_rate' => (float) $b['making_rate'],
            'making_default' => (float) $b['making_default_rate'],
            'making_min' => (float) $b['making_min_rate'],
            'making_saved' => $bargained ? round((float) $b['making_at_default'] - (float) $b['making'], 2) : 0.0,
            'making_set' => $bargained ? (isset($set['amount']) ? ['amount' => (float) $b['making']] : ['rate' => (float) $b['making_rate']]) : null,
            'price' => $b['total'],
            'taxable' => $b['taxable'],
            'huid' => $b['huid_charge'], // the hallmarking part of the price, which has its own GST rate
            'making' => $b['making'],
            'making_net' => $b['making_net'], // the making charge after its discount: the part the bill's adjustment can reduce
            'breakup' => $pricing->breakupLines($b),
            'inVault' => Location::isInVault($item),
            'order' => $order ? "Order #{$order->id} for " . ($order->customer?->name ?? 'a customer') : null,
        ];
    }

    // Prices and the vault check are read again whenever the screen moves on. What staff changed on a piece stays.
    private function refreshCart(): void
    {
        foreach (Item::whereKey(array_keys($this->cart))->get() as $item) {
            $this->cart[$item->id] = $this->cartLine($item, $this->cart[$item->id]);
        }
    }

    // A weight or a note typed on a piece prices it again. Any other key sent for a line is overwritten by the same sum.
    public function updatedCart($value, string $key): void
    {
        $id = (int) explode('.', $key, 2)[0];
        if (isset($this->cart[$id]) && ($item = Item::find($id))) {
            $this->cart[$id] = $this->cartLine($item, $this->cart[$id]);
        }
    }

    // The making charge bargained on one piece, as a rate (the slider) or as the amount. Both stay inside what the pricing allows.
    public function setMaking(int $itemId, string $by, $value): void
    {
        if (! isset($this->cart[$itemId]) || ! ($item = Item::find($itemId))) {
            return;
        }
        $line = $this->cart[$itemId];
        $line['making_set'] = is_numeric($value) ? [$by === 'amount' ? 'amount' : 'rate' => (float) $value] : null;
        $this->cart[$itemId] = $this->cartLine($item, $line);
    }

    public function resetMaking(int $itemId): void
    {
        $this->setMaking($itemId, 'rate', null);
    }

    public function removeItem(int $itemId): void
    {
        unset($this->cart[$itemId]);
        // The charges that came with the piece go with it.
        $this->extras = array_values(array_filter($this->extras, fn ($e) => (int) ($e['item_id'] ?? 0) !== $itemId));
    }

    // The offer made when a piece is still recorded as in the vault: move it out to the counter first.
    public function moveToCounter(int $itemId): void
    {
        $item = Item::findOrFail($itemId);
        abort_unless(Auth::user()?->can('movement.create'), 403);
        if (Location::isInVault($item)) {
            Location::sendToFloor($item);
        }
        $this->cart[$itemId] = $this->cartLine($item, $this->cart[$itemId] ?? []);
        $this->resetErrorBag('cart');
        $this->dispatch('toast', message: "{$item->label} moved to the counter.", type: 'success');
    }

    /** The whole bill as plain text to paste into WhatsApp or a note (the Copy details button on Review). */
    private function detailText(): string
    {
        $t = $this->totals();
        $inr = fn ($n) => '₹' . number_format(abs((float) $n), 2);
        $customer = $this->customerObject;
        $lines = ['Radharani Jewellery Works', 'Bill, ' . now()->format('j M Y, g:i a'), ''];
        $lines[] = 'Customer: ' . ($customer ? $customer->name . ($customer->is_walk_in ? '' : ' · ' . $customer->phone) : '-');
        if ($customer && ! $customer->is_walk_in && trim((string) $customer->address) !== '') {
            $lines[] = 'Address: ' . trim(preg_replace('/\s+/', ' ', $customer->address));
        }
        $lines[] = 'Billed by: ' . (auth()->user()?->name ?? '-');
        foreach ($this->cart as $line) {
            $lines[] = '';
            $lines[] = $line['label'] . ' · ' . $line['category'] . ' · ' . number_format($line['weight'], 3) . ' g'
                . (abs($line['weight'] - $line['weight_listed']) > 0.0004 ? ' (record ' . number_format($line['weight_listed'], 3) . ' g)' : '');
            foreach ($line['breakup'] ?? [] as $b) {
                $lines[] = '  ' . $b['label'] . ($b['note'] ? ' (' . $b['note'] . ')' : '') . ': ' . ($b['amount'] < 0 ? '-' : '') . $inr($b['amount']);
            }
            $lines[] = '  Price before GST: ' . $inr($line['price']);
        }
        $lines[] = '';
        $lines[] = 'Pieces: ' . $inr($t['pieces']);
        if ($t['gst_jewellery'] > 0) {
            $lines[] = 'GST ' . rtrim(rtrim(number_format($t['rates'][0], 2), '0'), '.') . '% on jewellery (CGST + SGST): ' . $inr($t['gst_jewellery']);
        }
        if ($t['gst_hallmark'] > 0) {
            $lines[] = 'GST ' . rtrim(rtrim(number_format($t['rates'][1], 2), '0'), '.') . '% on hallmarking (CGST + SGST): ' . $inr($t['gst_hallmark']);
        }
        foreach ($this->extras as $e) {
            if (($e['name'] ?? '') !== '' && is_numeric($e['amount'] ?? null) && (float) $e['amount'] > 0) {
                $lines[] = $e['name'] . ': ' . $inr($e['amount']);
            }
        }
        if ($t['round_off'] != 0) {
            $lines[] = 'Round off: ' . ($t['round_off'] > 0 ? '+' : '-') . $inr($t['round_off']);
        }
        if ($t['final_discount'] > 0) {
            $lines[] = 'Discount: -' . $inr($t['final_discount']);
        }
        $lines[] = 'Total: ' . $inr($t['total']);
        foreach ($this->payments as $p) {
            if (is_numeric($p['amount'] ?? null) && (float) $p['amount'] > 0) {
                $lines[] = 'Paid by ' . (SalePayment::MODES[$p['mode']] ?? $p['mode']) . ': ' . $inr($p['amount']);
            }
        }
        $lines[] = 'Balance: ' . $inr($this->getBalanceProperty());
        if (trim($this->notes) !== '') {
            $lines[] = 'Note: ' . trim($this->notes);
        }

        return implode("\n", $lines);
    }

    // Cancel the bill at any point: nothing has been saved yet, so nothing is held or paid, and the form is simply left.
    public function cancelDraft()
    {
        session()->flash('toast', 'The sale was cancelled. Nothing was saved.');

        return $this->redirectRoute('sales.history');
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

    // When the sale was started from an exchange, its credit goes on a payment line, capped at the bill.
    // The line follows the bill if staff come back and change it, but never overwrites an amount they typed.
    private function prefillExchangeCredit(): void
    {
        foreach ($this->payments as $i => $p) {
            if (($p['exchange_id'] ?? null) && isset($p['auto']) && (string) $p['amount'] === (string) $p['auto']) {
                $amount = (string) min($this->exchangeLeft((int) $p['exchange_id'], $i), max(0, $this->getTotalProperty()));
                $this->payments[$i]['amount'] = $this->payments[$i]['auto'] = $amount;
            }
        }

        if ($this->exchangeId && ! collect($this->payments)->contains(fn ($p) => ($p['exchange_id'] ?? null) === $this->exchangeId)) {
            $this->addExchangeLine($this->exchangeId, true);
        }
    }

    private function addExchangeLine(int $exchangeId, bool $auto = false): void
    {
        $amount = min($this->exchangeLeft($exchangeId), max(0, round($this->getTotalProperty() - $this->getPaidTotalProperty(), 2)));
        if ($amount <= 0) {
            return;
        }
        $line = ['mode' => 'exchange', 'exchange_id' => $exchangeId, 'amount' => (string) $amount, 'note' => "Exchange #{$exchangeId}"];
        if ($auto) {
            $line['auto'] = (string) $amount;
        }

        $empty = collect($this->payments)->search(fn ($p) => (string) ($p['amount'] ?? '') === '' && empty($p['exchange_id']));
        if ($empty === false) {
            $this->payments[] = $line;
        } else {
            $this->payments[$empty] = $line;
        }
        $this->autoPaymentIndex = null; // the cash line is worked out again for what is left
    }

    /** Credit an exchange still has for this sale: what is left, less what other lines of this bill already use. */
    private function exchangeLeft(int $exchangeId, ?int $exceptLine = null): float
    {
        $tx = ExchangeTransaction::find($exchangeId);
        if (! $tx || $tx->customer_id !== $this->customerId) {
            return 0.0;
        }
        $others = collect($this->payments)->except($exceptLine)->filter(fn ($p) => ($p['exchange_id'] ?? null) === $exchangeId)
            ->sum(fn ($p) => is_numeric($p['amount'] ?? null) ? (float) $p['amount'] : 0);

        return max(0.0, round($tx->creditRemaining() - $others, 2));
    }

    // From the "Exchange credit" panel: put an exchange's credit on a payment line for what is still due.
    // The cash line that was filled in for the whole bill is worked out again for what is left.
    public function useCredit(int $exchangeId): void
    {
        if (collect($this->payments)->contains(fn ($p) => ($p['exchange_id'] ?? null) === $exchangeId)) {
            return;
        }

        $i = $this->autoPaymentIndex;
        if ($i !== null && isset($this->payments[$i]) && (string) ($this->payments[$i]['amount'] ?? '') === (string) $this->autoPaymentAmount) {
            $this->payments[$i]['amount'] = '';
        }
        if ($this->getBalanceProperty() <= 0) {
            $this->dispatch('toast', message: 'Nothing is left to pay on this bill.', type: 'info');

            return;
        }

        $this->addExchangeLine($exchangeId);
        $this->prefillPayment();
    }

    /** Advance the customer still has for this sale: their balance less what other lines of this bill already use. */
    private function advanceLeft(?int $exceptLine = null): float
    {
        if (! $this->customerId) {
            return 0.0;
        }
        $others = collect($this->payments)->except($exceptLine)->filter(fn ($p) => ($p['mode'] ?? '') === 'advance')
            ->sum(fn ($p) => is_numeric($p['amount'] ?? null) ? (float) $p['amount'] : 0);

        return max(0.0, round(app(AdvanceService::class)->balance($this->customerId) - $others, 2));
    }

    // From the "Advance" panel: put the customer's advance on a payment line for what is still due.
    public function useAdvance(): void
    {
        if (collect($this->payments)->contains(fn ($p) => ($p['mode'] ?? '') === 'advance')) {
            return;
        }
        $i = $this->autoPaymentIndex;
        if ($i !== null && isset($this->payments[$i]) && (string) ($this->payments[$i]['amount'] ?? '') === (string) $this->autoPaymentAmount) {
            $this->payments[$i]['amount'] = '';
        }
        $amount = min($this->advanceLeft(), max(0, $this->getBalanceProperty()));
        if ($amount <= 0) {
            $this->dispatch('toast', message: 'Nothing is left to pay on this bill.', type: 'info');

            return;
        }
        $line = ['mode' => 'advance', 'amount' => (string) $amount, 'note' => 'Advance held on the account'];
        $empty = collect($this->payments)->search(fn ($p) => (string) ($p['amount'] ?? '') === '' && empty($p['exchange_id']));
        $empty === false ? $this->payments[] = $line : $this->payments[$empty] = $line;
        $this->autoPaymentIndex = null;
        $this->prefillPayment();
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
        $amount = max(0, round($this->getTotalProperty() - $others, 2));
        if ($exchangeId = $this->payments[$i]['exchange_id'] ?? null) {
            $amount = min($amount, $this->exchangeLeft((int) $exchangeId, $i));
        }
        if (($this->payments[$i]['mode'] ?? '') === 'advance') {
            $amount = min($amount, $this->advanceLeft($i));
        }
        $this->payments[$i]['amount'] = (string) $amount;
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

    /**
     * Every figure of the bill: pieces, the adjustment off the making charge, GST, extra charges, round-off, total.
     * Worked out afresh from the cart on every call, never cached mid-request.
     */
    public function totals(): array
    {
        $rates = [is_numeric($this->gstJewellery) ? (float) $this->gstJewellery : 0.0, is_numeric($this->gstHallmark) ? (float) $this->gstHallmark : 0.0];
        $lines = collect($this->cart)->map(fn ($c) => ['total' => (float) $c['price'], 'taxable' => (float) ($c['taxable'] ?? $c['price'] - ($c['huid'] ?? 0)), 'huid_charge' => (float) ($c['huid'] ?? 0), 'making' => (float) ($c['making'] ?? 0), 'making_net' => (float) ($c['making_net'] ?? 0)]);
        $v = is_numeric($this->finalValue) ? (float) $this->finalValue : 0.0;

        $pricing = app(PricingService::class);
        // The making charge is bargained per piece, so the bill carries no adjustment of its own any more: only the discount at the end.
        $bill = $pricing->billTotals($lines, $this->getExtrasTotalProperty(), 'flat', 0.0, $rates[0], $rates[1]);

        return $pricing->withFinalDiscount($bill, $this->finalType, $v, $rates[0]) + ['rates' => $rates];
    }

    public function gstDetail(): array
    {
        return $this->totals();
    }

    public function getGstProperty(): float
    {
        return $this->totals()['gst'];
    }

    // The bill including GST, extra charges and round-off, less the discount at the end: this is what is paid and kept as the sale's total.
    public function getTotalProperty(): float
    {
        return $this->totals()['total'];
    }

    public function getPaidTotalProperty(): float
    {
        return round(collect($this->payments)->sum(fn ($p) => is_numeric($p['amount'] ?? null) ? (float) $p['amount'] : 0), 2);
    }

    public function getBalanceProperty(): float
    {
        return round($this->getTotalProperty() - $this->getPaidTotalProperty(), 2); // not the cached accessors: this is read mid-request
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

        try {
        $sale = DB::transaction(function () use ($items, $pricing, $extras) {
            // Each piece is priced again here from the piece and what was changed on the bill, never from the cart's own figures.
            $breakdowns = $items->mapWithKeys(function ($i) use ($pricing) {
                $c = $this->cart[$i->id] ?? [];
                $set = is_array($c['making_set'] ?? null) ? $c['making_set'] : [];
                $w = trim((string) ($c['weight_input'] ?? ''));

                return [$i->id => $pricing->breakdown($i, [
                    'weight' => is_numeric($w) && (float) $w > 0 ? (float) $w : null,
                    'making_rate' => $set['rate'] ?? null,
                    'making_amount' => $set['amount'] ?? null,
                ])];
            });
            $prices = $breakdowns->map(fn ($b) => $b['total']);
            $extrasTotal = round(array_sum(array_column($extras, 'amount')), 2);
            $v = is_numeric($this->finalValue) ? (float) $this->finalValue : 0.0;

            // GST at the owner's rates, kept with the sale so a later change never alters it.
            $rates = [(float) $this->gstJewellery, (float) $this->gstHallmark];
            $bill = $pricing->withFinalDiscount($pricing->billTotals($breakdowns, $extrasTotal, 'flat', 0.0, $rates[0], $rates[1]), $this->finalType, $v, $rates[0]);
            $gst = $bill;

            $sale = Sale::create([
                'customer_id' => $this->customerId,
                'referral_customer_id' => $this->referralCustomerId,
                'invoice_number' => 'RESV-' . now()->format('YmdHis') . '-' . $this->customerId, // the Tally bill number replaces it at verification
                'type' => 'sale',
                'additional_charges' => $extras ?: null,
                'discount' => 0, // the making charge is bargained per piece now; older sales keep their bill-level adjustment here
                'final_discount' => $bill['final_discount'],
                'accountant_note' => trim($this->notes) ?: null,
                'order_override_note' => collect($this->cart)->contains(fn ($c) => $c['order'] !== null) ? trim($this->overrideNote) : null,
                'gst_jewellery_percent' => $rates[0],
                'gst_hallmark_percent' => $rates[1],
                'gst_amount' => $gst['gst'],
                'gst_hallmark_amount' => $gst['gst_hallmark'],
                'round_off' => $bill['round_off'],
                'total' => $bill['total'],
                'confirmed_by_accountant' => false,
                'created_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                $b = $breakdowns[$item->id];
                $c = $this->cart[$item->id] ?? [];
                $sale->items()->attach($item->id, [
                    'price_at_sale' => $prices[$item->id],
                    'weight_listed' => $b['weight_listed'],
                    'weight_at_sale' => $b['weight'],
                    'weight_note' => abs($b['weight'] - $b['weight_listed']) > 0.0004 ? (trim((string) ($c['weight_note'] ?? '')) ?: null) : null,
                    'making_kind' => $b['making_default_rate'] > 0 ? $b['making_kind'] : null,
                    'making_default_rate' => $b['making_default_rate'] > 0 ? $b['making_default_rate'] : null,
                    'making_rate' => $b['making_default_rate'] > 0 ? $b['making_rate'] : null,
                    'making_amount' => $b['making'],
                ]);
                $item->update(['status' => 'reserved']); // per item, so each piece's history shows it
            }

            foreach ($this->payments as $p) {
                if (is_numeric($p['amount'] ?? null) && (float) $p['amount'] > 0) {
                    $exchangeId = $p['mode'] === 'exchange' ? (int) ($p['exchange_id'] ?? 0) : null;
                    if ($exchangeId) {
                        // Takes the credit under a lock, so it can not be spent twice.
                        app(ExchangeCredit::class)->apply($exchangeId, 'sale', $sale->id, (float) $p['amount'], "Sale #{$sale->id}");
                    }
                    if ($p['mode'] === 'advance') {
                        // Takes the advance under a lock, so it can not be spent twice.
                        app(AdvanceService::class)->useOnSale($this->customerId, $sale->id, (float) $p['amount']);
                    }
                    SalePayment::create(['sale_id' => $sale->id, 'mode' => $p['mode'], 'exchange_transaction_id' => $exchangeId ?: null, 'amount' => round((float) $p['amount'], 2), 'note' => $p['note'] ?? null, 'user_id' => Auth::id()]);
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
        } catch (InsufficientAdvance $e) {
            $this->step = 4;
            $this->addError('payments', $e->getMessage());

            return;
        } catch (InsufficientExchangeCredit $e) {
            $this->step = 4;
            $this->addError('payments', $e->getMessage());

            return;
        }

        \App\Support\MoneyMessages::saleEntered($sale);

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
                ? Customer::real()->where(fn ($q) => $q->where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "%{$this->customerSearch}%"))->limit(8)->get()
                : collect(),
            'walkIn' => $this->step === 3 ? Customer::walkIn() : null,
            'copyText' => $this->step === 5 ? $this->detailText() : '',
            'itemResults' => $this->itemSearch !== ''
                ? Item::where('status', 'in_stock')->whereNotIn('id', array_keys($this->cart))->searchAnything($this->itemSearch)->limit(8)->get()
                : collect(),
            'orderModel' => $this->orderId ? Order::with('customer:id,name', 'images')->find($this->orderId) : null,
            'exchangeCredit' => $this->exchangeId ? ExchangeTransaction::find($this->exchangeId)?->creditRemaining() : null,
            'waitingReview' => $this->orderId ? (Order::find($this->orderId)?->piecesAwaitingReview() ?? 0) : 0,
            'modes' => SalePayment::NEW_MODES,
            'modeLabels' => SalePayment::MODES,
            'advanceHeld' => $this->customerId && $this->step === 4 && ! collect($this->payments)->contains(fn ($p) => ($p['mode'] ?? '') === 'advance') ? app(AdvanceService::class)->balance($this->customerId) : 0.0,
            'gst' => $this->gstDetail(),
            'credits' => $this->customerId && $this->step === 4
                ? app(ExchangeCredit::class)->available($this->customerId)
                    ->reject(fn ($x) => collect($this->payments)->contains(fn ($p) => ($p['exchange_id'] ?? null) === $x->id))->values()
                : collect(),
            'chargeNames' => $this->step === 2 ? \App\Support\ChargeNames::list() : [],
            'canOverride' => $this->canOverride(),
            'hasOrderWarning' => collect($this->cart)->contains(fn ($c) => $c['order'] !== null),
        ])->layout('components.layouts.app', ['title' => 'New Sale — Radharani Jewellery']);
    }
}
