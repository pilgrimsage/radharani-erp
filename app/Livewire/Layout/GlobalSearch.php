<?php
namespace App\Livewire\Layout;

use App\Models\Customer\Customer;
use App\Models\Orders\Order;
use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use Livewire\Component;

// Topbar search — mounted once in the app shell (components/layouts/app.blade.php),
// live-searches boxes/packets/items/customers/orders as staff type. Read-only, no writes.
class GlobalSearch extends Component
{
    public string $query = '';

    public function getResultsProperty(): array
    {
        if (strlen($this->query) < 2) {
            return [];
        }

        $boxes = Box::where('code', 'like', "%{$this->query}%")
            ->orWhere('label', 'like', "%{$this->query}%")
            ->limit(5)->get();

        $packets = Packet::with('box:id,code')
            ->where('code', 'like', "%{$this->query}%")
            ->orWhere('label', 'like', "%{$this->query}%")
            ->limit(5)->get();

        $items = Item::where('huid_code', 'like', "%{$this->query}%")
            ->orWhere('internal_code', 'like', "%{$this->query}%")
            ->orWhere('category', 'like', "%{$this->query}%")
            ->limit(5)->get();

        $customers = Customer::where('name', 'like', "%{$this->query}%")
            ->orWhere('phone', 'like', "%{$this->query}%")
            ->limit(5)->get();

        $orders = Order::where('product_description', 'like', "%{$this->query}%")
            ->limit(5)->get();

        return [
            'boxes' => $boxes,
            'packets' => $packets,
            'items' => $items,
            'customers' => $customers,
            'orders' => $orders,
        ];
    }

    public function render()
    {
        return view('livewire.layout.global-search');
    }
}
