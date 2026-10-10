<?php
namespace App\Livewire\Reports;

use App\Models\Location;
use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use Livewire\Component;

/** What is at one location right now, and how it got there (8 Oct change list, 3.1). */
class LocationDetail extends Component
{
    public Location $location;

    public function mount(Location $location): void
    {
        $this->location = $location;
    }

    public function render()
    {
        $entries = Location::floorEntries();
        $vault = Location::vault();
        $isVault = $this->location->id === $vault?->id;

        // Containers recorded as being here (the vault holds everything not recorded elsewhere).
        $here = $entries->filter(fn ($e) => $e['location_id'] === $this->location->id);
        $boxes = Box::whereIn('id', $here->where('type', 'box')->pluck('id'))->get();
        $packets = Packet::with('box:id,code')->whereIn('id', $here->where('type', 'packet')->pluck('id'))->get();

        $ids = collect(Location::itemLocations())->filter(fn ($l) => $l === $this->location->id)->keys();
        $items = Item::with('packet.box')->whereIn('id', $ids)->orderBy('category')->limit(300)->get();

        $recent = $this->location->movements()->with('user:id,name', 'doneBy:id,name')->latest('id')->limit(15)->get();

        return view('livewire.reports.location-detail', [
            'boxes' => $boxes,
            'packets' => $packets,
            'items' => $items,
            'itemCount' => $ids->count(),
            'weight' => Item::whereIn('id', $ids)->sum('weight'),
            'isVault' => $isVault,
            'recent' => $recent,
        ])->layout('components.layouts.app', ['title' => $this->location->name . ' · Radharani Jewellery ERP']);
    }
}
