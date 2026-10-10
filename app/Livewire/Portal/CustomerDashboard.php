<?php
namespace App\Livewire\Portal;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Customer portal: My account. Read-only view of the signed-in customer's
 * own purchases, instalment schemes and referrals, inside
 * the public website's layout.
 */
class CustomerDashboard extends Component
{
    public const TABS = ['purchases', 'installments', 'referrals'];

    #[Url(as: 'tab', except: 'purchases')]
    public string $tab = 'purchases';

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'purchases';
    }

    public function logout()
    {
        Auth::guard('customer')->logout();
        session()->invalidate();
        session()->regenerateToken();

        // Full page load (not wire:navigate): the website's header and scripts
        // need to boot fresh, and now show "Sign in" again.
        return $this->redirect(route('home'));
    }

    public function render()
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'purchases';
        }

        $customer = Auth::guard('customer')->user()->load([
            'sales' => fn ($q) => $q->orderByDesc('created_at'),
            'sales.items.images',
            'installmentSchemes' => fn ($q) => $q->orderByDesc('start_date'),
            'installmentSchemes.payments' => fn ($q) => $q->orderBy('paid_on'),
        ]);

        $saleInvoices = $customer->sales->pluck('invoice_number', 'id');

        return view('livewire.portal.customer-dashboard', [
            'customer' => $customer,
            // People who bought with this customer's code, and the referral points earned (8 Oct change list, 15.1).
            'referredBuyers' => \App\Models\Sales\Sale::where('referral_customer_id', $customer->id)->where('confirmed_by_accountant', true)->with('customer:id,name')->get()->groupBy('customer_id'),
            'referralPoints' => (int) \App\Models\Customer\ReferralPoint::where('customer_id', $customer->id)->sum('points'),
            'saleInvoices' => $saleInvoices,
            'firstName' => strtok(trim($customer->name), ' ') ?: $customer->name,
        ])->layout('components.layouts.portal', ['title' => 'My account | Radharani Jewellery Works']);
    }
}
