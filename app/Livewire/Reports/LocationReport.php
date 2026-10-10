<?php
namespace App\Livewire\Reports;

use App\Models\Location;
use App\Models\Movement\Movement;
use App\Models\Stock\Item;
use Livewire\Component;

/**
 * Location Report: pieces and weight by where they are right now. In-store places
 * are the owner-managed locations; pieces away from the shop are grouped by where
 * they went. Weight and counts only (8 Oct change list, 1.7 and 3.1).
 */
class LocationReport extends Component
{
    public function render()
    {
        $at = Location::itemLocations(); // in-stock pieces, item_id => location_id
        $weights = Item::where('status', 'in_stock')->pluck('weight', 'id');

        $rows = Location::orderBy('sort_order')->orderBy('id')->get()->map(function ($loc) use ($at, $weights) {
            $ids = array_keys(array_filter($at, fn ($l) => $l === $loc->id));

            return [
                'location' => $loc,
                'label' => $loc->name,
                'qty' => count($ids),
                'weight' => collect($ids)->sum(fn ($id) => (float) ($weights[$id] ?? 0)),
            ];
        });

        // Away from the shop or in a process: grouped by what the piece is doing.
        $away = Item::whereIn('status', ['dispatched', 'pending_review', 'reserved'])->get(['id', 'status', 'weight'])
            ->groupBy(function ($item) {
                if ($item->status !== 'dispatched') {
                    return $item->status === 'pending_review' ? 'Awaiting review' : 'Held for a sale';
                }
                $m = $item->currentMovement();

                return $m ? Movement::LABELS[$m->movement_type] ?? 'Away' : 'Away';
            })->map(fn ($g, $label) => ['location' => null, 'label' => $label, 'qty' => $g->count(), 'weight' => $g->sum('weight')])->values();

        $all = $rows->concat($away);

        return view('livewire.reports.location-report', [
            'rows' => $all,
            'stats' => [
                'locations' => $rows->count(),
                'items' => $all->sum('qty'),
                'weight' => $all->sum('weight'),
            ],
        ])->layout('components.layouts.app', ['title' => 'Location Report · Radharani Jewellery ERP']);
    }
}
