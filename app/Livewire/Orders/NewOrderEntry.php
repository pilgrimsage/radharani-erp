<?php
namespace App\Livewire\Orders;

use App\Models\Customer\Customer;
use App\Models\Movement\RateLog;
use App\Models\Orders\Order;
use App\Models\Orders\OrderImage;
use App\Models\Stock\Item;
use App\Models\Stock\ItemCategory;
use App\Services\PhotoCompressionService;
use App\Support\MessageTemplates;
use App\Support\Phone;
use App\Support\StockLookup;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * New custom order as a short step form (8 Oct change list, 10.1): the customer, the product
 * with reference images, then the advance. Orders are a distinct lifecycle from Sales: a
 * pre-commitment that later converts into a sale. Rate lock: if the customer pays the full
 * value at order time the rate is locked to that day; otherwise the rate at delivery applies.
 */
class NewOrderEntry extends Component
{
    use WithFileUploads;

    public const STEPS = [1 => 'Customer', 2 => 'Product', 3 => 'Advance'];

    public int $step = 1;
    public int $reach = 1;

    // 1. customer
    public string $customerSearch = '';
    public ?int $customerId = null;
    public bool $addingCustomer = false;
    public string $newName = '';
    public string $newPhone = '';

    // 2. product
    public string $productDescription = '';
    public string $category = '';
    public ?string $metal = 'gold';
    public $estimatedWeight = '';
    public ?string $expectedReadyDate = null;
    public string $sourcing = 'karigar';
    public string $existingItemSearch = '';
    public ?int $existingItemId = null;
    public array $images = [];

    // 3. advance
    public bool $fullPaymentNow = false;
    public $estimatedValue = '';
    public $depositAmount = '';

    public function goToStep(int $n): void
    {
        if ($n >= 1 && $n <= 3 && $n <= $this->reach) {
            $this->resetValidation();
            $this->step = $n;
        }
    }

    public function next(): void
    {
        $this->resetValidation();
        if ($this->step === 1 && ! $this->customerId) {
            $this->addError('customerId', 'Choose the customer, or add a new one.');

            return;
        }
        if ($this->step === 2) {
            $this->validate([
                'productDescription' => 'required|string|max:200',
                'category' => 'nullable|string|max:50',
                'metal' => 'nullable|in:gold,silver,titanium,platinum',
                'estimatedWeight' => 'nullable|numeric|min:0.001',
                'expectedReadyDate' => 'nullable|date|after_or_equal:today',
                'sourcing' => 'required|in:stock,karigar,bought_finished,bought_unhallmarked,bought_unfinished',
                'images.*' => 'image|max:5120',
            ], ['productDescription.required' => 'Describe the product.']);
            if ($this->sourcing === 'stock' && ! $this->existingItemId) {
                $this->addError('existingItemId', 'Choose the piece in stock this order is for.');

                return;
            }
        }
        $this->step = min(3, $this->step + 1);
        $this->reach = max($this->reach, $this->step);
    }

    public function chooseCustomer(int $id): void
    {
        $this->customerId = $id;
        $this->customerSearch = '';
        $this->addingCustomer = false;
        $this->resetErrorBag('customerId');
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
    }

    public function saveNewCustomer(): void
    {
        $this->newPhone = Phone::normalize($this->newPhone);
        $this->validate(['newName' => ['required', 'string', 'max:100'], 'newPhone' => ['required', 'digits:10']],
            ['newPhone.digits' => 'Enter a 10 digit mobile number.'], ['newName' => 'name', 'newPhone' => 'phone']);

        $c = Customer::where('phone', $this->newPhone)->first() ?? Customer::create(['name' => $this->newName, 'phone' => $this->newPhone, 'status' => 'order_pending']);
        $this->reset(['newName', 'newPhone']);
        $this->chooseCustomer($c->id);
    }

    public function chooseItem(int $id): void
    {
        $this->existingItemId = $id;
        $this->existingItemSearch = '';
        $this->resetErrorBag('existingItemId');
        if ($item = Item::find($id)) {
            $this->metal = $item->metal ?: $this->metal;
            $this->category = $item->category;
            $this->estimatedWeight = (string) (float) $item->weight;
            $this->productDescription = $this->productDescription ?: trim($item->category . ' ' . $item->label);
        }
    }

    public function clearItem(): void
    {
        $this->existingItemId = null;
    }

    public function removeImage(int $index): void
    {
        array_splice($this->images, $index, 1);
    }

    public function updatedSourcing(): void
    {
        if ($this->sourcing !== 'stock') {
            $this->existingItemId = null;
        }
    }

    public function submit()
    {
        $this->validate([
            'customerId' => 'required|exists:customers,id',
            'estimatedValue' => $this->fullPaymentNow ? 'required|numeric|min:1' : 'nullable|numeric|min:0',
            'depositAmount' => 'nullable|numeric|min:0',
        ], ['estimatedValue.required' => 'Enter the full value to lock the rate against.']);

        $advance = $this->fullPaymentNow ? (float) $this->estimatedValue : (float) ($this->depositAmount ?: 0);
        $rateLog = $this->fullPaymentNow && $this->metal ? RateLog::latestFor($this->metal) : null;

        $order = DB::transaction(function () use ($advance, $rateLog) {
            $order = Order::create([
                'customer_id' => $this->customerId,
                'product_description' => $this->productDescription,
                'category' => $this->category ?: null,
                'metal' => $this->metal,
                'estimated_weight' => $this->estimatedWeight ?: null,
                'estimated_value' => (float) ($this->estimatedValue ?: 0),
                'advance_amount' => $advance,
                'full_payment_now' => $this->fullPaymentNow,
                'rate_locked' => (bool) $rateLog,
                'locked_rate' => $rateLog?->rate,
                'locked_at' => $rateLog ? now() : null,
                'in_stock_item_id' => $this->sourcing === 'stock' ? $this->existingItemId : null,
                'out_of_stock' => $this->sourcing !== 'stock',
                'sourcing' => $this->sourcing,
                'status' => 'placed',
                'expected_ready_date' => $this->expectedReadyDate ?: null,
                'created_by' => auth()->id(),
            ]);

            // Reference images go through the standard photo service, never saved as uploaded.
            foreach ($this->images as $file) {
                $order->images()->create(['path' => app(PhotoCompressionService::class)->store($file, 'orders/reference'), 'created_by' => auth()->id()]);
            }

            return $order;
        });

        MessageTemplates::queue('order_accepted', $order->customer, MessageTemplates::orderAccepted($order), 'order', $order->id);

        session()->flash('toast', "Order #{$order->id} created.");

        return $this->redirectRoute('orders.show', $order);
    }

    public function render()
    {
        return view('livewire.orders.new-order-entry', [
            'steps' => self::STEPS,
            'customer' => $this->customerId ? Customer::find($this->customerId) : null,
            'item' => $this->existingItemId ? Item::find($this->existingItemId) : null,
            'sourcings' => Order::SOURCING,
            'subcategories' => ItemCategory::active()->when($this->metal, fn ($q) => $q->where('metal', $this->metal))->orderBy('name')->get(['id', 'name']),
            'customerResults' => $this->customerSearch !== ''
                ? Customer::where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "%{$this->customerSearch}%")->limit(8)->get()
                : collect(),
            'itemResults' => $this->existingItemSearch !== ''
                ? Item::where('status', 'in_stock')->searchAnything($this->existingItemSearch)->limit(8)->get()
                : collect(),
        ])->layout('components.layouts.app', ['title' => 'New Custom Order — Radharani Jewellery']);
    }
}
