<?php
namespace App\Livewire\Movement;

use App\Livewire\Movement\Concerns\PicksItems;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerMaterialJob;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Movement\Movement;
use App\Models\Purchase\Vendor;
use App\Models\Stock\Item;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Sending work out to a karigar. Three situations (#5), each asking only
 * what applies to it:
 *  - tagged:            pieces from stock going for repair (movements, same piece comes back)
 *  - customer_material: a customer's own untagged metal (customer_material_jobs, never shop stock)
 *  - raw_material:      raw metal to be made into a new piece (karigar_raw_batches)
 */
class KarigarDispatch extends Component
{
    use PicksItems, \App\Livewire\Movement\Concerns\HasDoneBy;

    public const METALS = ['gold' => 'Gold', 'silver' => 'Silver', 'platinum' => 'Platinum', 'titanium' => 'Titanium'];
    public const WORK = ['Repair', 'Polish', 'Resize', 'Stone setting', 'Rhodium', 'Soldering'];

    #[Url(as: 'type', except: 'tagged')]
    public string $situation = 'tagged'; // tagged | customer_material | raw_material

    public ?int $vendorId = null;
    public ?string $expectedReturn = null;
    public string $note = '';
    public string $work = 'Repair';

    // customer_material
    public string $customerSearch = '';
    public ?int $customerId = null;
    public string $description = '';

    // customer_material + raw_material
    public $weight = '';
    public string $metal = 'gold';

    // raw_material
    public string $purity = '';

    public function mount(): void
    {
        $this->expectedReturn = today()->addDays(7)->toDateString();
    }

    protected function pickableStatuses(): array
    {
        return ['in_stock'];
    }

    public function setSituation(string $situation): void
    {
        // Raw material is issued as a batch on the main Karigar screen now.
        if (! in_array($situation, ['tagged', 'customer_material'], true)) {
            return;
        }
        $this->situation = $situation;
        $this->resetValidation();
    }

    public function chooseCustomer(int $id): void
    {
        $this->customerId = $id;
        $this->customerSearch = '';
        $this->resetErrorBag('customerId');
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
    }

    public function submit(): void
    {
        match ($this->situation) {
            'customer_material' => $this->dispatchCustomerMaterial(),
            'raw_material' => $this->dispatchRawMaterial(),
            default => $this->dispatchTagged(),
        };
    }

    private function commonRules(): array
    {
        return [
            'vendorId' => ['required', Rule::exists('vendors', 'id')->where('type', 'karigar')],
            'expectedReturn' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function validationMessages(): array
    {
        return [
            'vendorId.required' => 'Choose the karigar.',
            'basket.required' => 'Add at least one piece.',
            'customerId.required' => 'Choose the customer whose material this is.',
        ];
    }

    private function dispatchTagged(): void
    {
        $this->validate($this->commonRules() + [
            'basket' => ['required', 'array', 'min:1'],
            'work' => ['required', 'string', 'max:50'],
        ], $this->validationMessages());

        $vendor = Vendor::findOrFail($this->vendorId);
        $items = Item::whereIn('id', $this->basket)->where('status', 'in_stock')->get();

        DB::transaction(function () use ($items, $vendor) {
            foreach ($items as $item) {
                Movement::create([
                ...$this->doneByAttributes(),
                    'trackable_type' => 'item',
                    'trackable_id' => $item->id,
                    'movement_type' => 'karigar_out',
                    'purpose_label' => $this->work,
                    'user_id' => Auth::id(),
                    'counterparty' => $vendor->name,
                    'expected_return' => $this->expectedReturn ?: null,
                    'weight_at_dispatch' => $item->weight,
                    'note' => $this->note ?: null,
                ]);
                $item->update(['status' => 'dispatched']);
            }
        });

        $this->dispatch('toast', message: "{$items->count()} " . \Illuminate\Support\Str::plural('piece', $items->count()) . " sent to {$vendor->name} for {$this->work}.", type: 'success');
        $this->reset(['basket', 'note']);
    }

    private function dispatchCustomerMaterial(): void
    {
        $this->validate($this->commonRules() + [
            'customerId' => ['required', 'exists:customers,id'],
            'description' => ['required', 'string', 'max:150'],
            'weight' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'metal' => ['required', Rule::in(array_keys(self::METALS))],
        ], $this->validationMessages(), ['description' => 'what it is']);

        $customer = Customer::findOrFail($this->customerId);
        $vendor = Vendor::findOrFail($this->vendorId);

        // Never shop stock: no item ID, tracked against the customer directly.
        CustomerMaterialJob::create([
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'description' => $this->description,
            'weight_out' => $this->weight,
            'metal' => $this->metal,
            'expected_return' => $this->expectedReturn ?: null,
            'status' => 'out',
            'note' => $this->note ?: null,
            'user_id' => Auth::id(),
        ]);

        $this->dispatch('toast', message: "{$customer->name}'s " . number_format((float) $this->weight, 3) . " g {$this->metal} sent to {$vendor->name}.", type: 'success');
        $this->reset(['customerId', 'customerSearch', 'description', 'weight', 'note']);
    }

    private function dispatchRawMaterial(): void
    {
        $this->validate($this->commonRules() + [
            'weight' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'metal' => ['required', Rule::in(array_keys(self::METALS))],
            'purity' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string', 'max:50'],
        ], $this->validationMessages(), ['description' => 'what to make']);

        $vendor = Vendor::findOrFail($this->vendorId);

        // What leaves (raw metal) and what returns (a finished, untagged piece)
        // are not the same physical thing, so this is its own table. The piece
        // gets created at Karigar Return.
        KarigarRawBatch::create([
            'vendor_id' => $vendor->id,
            'weight_out' => $this->weight,
            'metal' => $this->metal,
            'purity' => $this->purity ?: null,
            'purpose_label' => $this->description ?: null,
            'expected_return' => $this->expectedReturn ?: null,
            'status' => 'dispatched',
            'note' => $this->note ?: null,
            'user_id' => Auth::id(),
        ]);

        $this->dispatch('toast', message: number_format((float) $this->weight, 3) . " g raw {$this->metal} issued to {$vendor->name}.", type: 'success');
        $this->reset(['weight', 'purity', 'description', 'note']);
    }

    public function render()
    {
        $openPieces = Movement::openItemDispatches(['karigar'])->with('item')->orderBy('expected_return')->get();
        $openCustomer = CustomerMaterialJob::with(['customer:id,name', 'vendor:id,name'])->where('status', 'out')->orderBy('expected_return')->get();
        $openRaw = KarigarRawBatch::with('vendor:id,name')->whereIn('status', ['dispatched', 'partially_returned'])->orderBy('expected_return')->get();

        $out = collect()
            ->concat($openPieces->map(fn ($m) => [
                'kind' => 'Repair', 'icon' => 'gem', 'code' => $m->item?->label, 'detail' => $m->item?->category . ' · ' . $m->purpose_label,
                'karigar' => $m->counterparty, 'weight' => $m->weight_at_dispatch, 'due' => $m->expected_return, 'since' => $m->created_at,
            ]))
            ->concat($openCustomer->map(fn ($j) => [
                'kind' => 'Customer', 'icon' => 'user', 'code' => $j->customer?->name, 'detail' => $j->description,
                'karigar' => $j->vendor?->name, 'weight' => $j->weight_out, 'due' => $j->expected_return, 'since' => $j->created_at,
            ]))
            ->concat($openRaw->map(fn ($b) => [
                'kind' => 'Raw', 'icon' => 'flame', 'code' => 'Batch #' . $b->id, 'detail' => ucfirst($b->metal) . ($b->purpose_label ? ' · ' . $b->purpose_label : ''),
                'karigar' => $b->vendor?->name, 'weight' => $b->weight_out, 'due' => $b->expected_return, 'since' => $b->created_at,
            ]))
            ->sortBy(fn ($r) => $r['due']?->timestamp ?? PHP_INT_MAX)->values();

        return view('livewire.movement.karigar-dispatch', [
            'karigars' => Vendor::where('type', 'karigar')->orderBy('name')->get(),
            'basketItems' => $this->basketItems(),
            'pickResults' => $this->situation === 'tagged' ? $this->pickResults() : collect(),
            'customer' => $this->customerId ? Customer::find($this->customerId) : null,
            'customerResults' => $this->situation === 'customer_material' && ! $this->customerId && trim($this->customerSearch) !== ''
                ? Customer::where(fn ($q) => $q->where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "%{$this->customerSearch}%"))
                    ->orderBy('name')->limit(8)->get()
                : collect(),
            'out' => $out,
            'overdue' => $out->filter(fn ($r) => $r['due'] && $r['due']->isBefore(today()))->count(),
            'purities' => \App\Livewire\Stock\ItemForm::PURITIES[$this->metal] ?? [],
        ])->layout('components.layouts.app', ['title' => 'Karigar Dispatch · Radharani Jewellery']);
    }
}
