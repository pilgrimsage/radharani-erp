<?php
namespace App\Livewire\Orders;

use App\Models\Customer\Customer;
use App\Models\Movement\RateLog;
use App\Models\Orders\Order;
use App\Models\Stock\Item;
use Livewire\Component;

/**
 * Custom Orders — New Entry.
 *
 * Orders are a distinct lifecycle from Sales, not a sale sub-type — a
 * pre-commitment that later converts into a sale (see `orders` migration
 * and docs/DEVELOPER_GUIDE.md). Rate-locking rule: if the customer pays
 * the full value at order time, the rate is locked to that date; otherwise
 * the rate at delivery applies.
 */
class NewOrderEntry extends Component
{
    public string $customerSearch = '';
    public ?int $customerId = null;
    public string $productDescription = '';
    public string $category = '';
    public ?string $metal = null;
    public bool $inStock = false;
    public string $existingItemSearch = '';
    public ?int $existingItemId = null;

    public bool $fullPaymentNow = false;
    public float $depositAmount = 0;
    public ?float $estimatedWeight = null;
    public float $estimatedValue = 0;
    public ?string $expectedReadyDate = null;

    protected function rules(): array
    {
        return [
            'customerId' => 'required|exists:customers,id',
            'productDescription' => 'required|string|max:200',
            'category' => 'nullable|string|max:50',
            'metal' => $this->fullPaymentNow ? 'required|in:gold,silver,titanium,platinum' : 'nullable|in:gold,silver,titanium,platinum',
            'estimatedWeight' => 'nullable|numeric|min:0.001',
            'estimatedValue' => 'required|numeric|min:0',
            'depositAmount' => 'nullable|numeric|min:0',
            'expectedReadyDate' => 'nullable|date|after_or_equal:today',
            'existingItemId' => 'nullable|exists:items,id',
        ];
    }

    public function submit()
    {
        $this->validate();

        $advanceAmount = $this->fullPaymentNow ? $this->estimatedValue : $this->depositAmount;

        $rateLocked = false;
        $lockedRate = null;
        $lockedAt = null;

        if ($this->fullPaymentNow) {
            $rateLog = RateLog::latestFor($this->metal);
            $rateLocked = true;
            $lockedRate = $rateLog?->rate;
            $lockedAt = now();
        }

        $order = Order::create([
            'customer_id' => $this->customerId,
            'product_description' => $this->productDescription,
            'category' => $this->category ?: null,
            'metal' => $this->metal,
            'estimated_weight' => $this->estimatedWeight ?: null,
            'estimated_value' => $this->estimatedValue,
            'advance_amount' => $advanceAmount,
            'full_payment_now' => $this->fullPaymentNow,
            'rate_locked' => $rateLocked,
            'locked_rate' => $lockedRate,
            'locked_at' => $lockedAt,
            'in_stock_item_id' => $this->inStock ? $this->existingItemId : null,
            'out_of_stock' => ! $this->inStock,
            'status' => 'placed',
            'expected_ready_date' => $this->expectedReadyDate ?: null,
            'created_by' => auth()->id(),
        ]);

        \App\Support\MessageTemplates::queue('order_accepted', $order->customer, \App\Support\MessageTemplates::orderAccepted($order), 'order', $order->id);

        session()->flash('toast', "Order #{$order->id} created.");

        return $this->redirectRoute('orders.show', $order);
    }

    public function render()
    {
        return view('livewire.orders.new-order-entry', [
            'customerResults' => $this->customerSearch
                ? Customer::where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "%{$this->customerSearch}%")->limit(8)->get()
                : collect(),
            'itemResults' => $this->existingItemSearch
                ? Item::where('status', 'in_stock')
                    ->where(function ($q) {
                        $q->where('huid_code', 'like', "%{$this->existingItemSearch}%")
                            ->orWhere('internal_code', 'like', "%{$this->existingItemSearch}%");
                    })
                    ->limit(8)->get()
                : collect(),
        ])->layout('components.layouts.app', ['title' => 'New Custom Order — Radharani Jewellery']);
    }
}
