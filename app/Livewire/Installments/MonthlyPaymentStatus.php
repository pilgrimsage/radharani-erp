<?php
namespace App\Livewire\Installments;

use App\Models\Customer\InstallmentPayment;
use App\Models\Customer\InstallmentScheme;
use App\Models\Notification\PendingNotification;
use Livewire\Component;

class MonthlyPaymentStatus extends Component
{
    // "Due" reminder — the moment this month's instalment is outstanding,
    // not yet the moment it's paid. Kept separate from markPaid() so a
    // reminder queues once per month, independent of when/whether the
    // payment eventually gets recorded.
    public function sendReminder(int $schemeId)
    {
        $scheme = InstallmentScheme::with('customer')->findOrFail($schemeId);

        \App\Support\MessageTemplates::queue('installment_reminder', $scheme->customer, \App\Support\MessageTemplates::schemeReminder($scheme), 'installment_scheme', $scheme->id);

        $this->dispatch('toast', message: "Reminder queued for {$scheme->customer->name}'s ".now()->format('F Y').' instalment.', type: 'success');
    }

    public function markPaid(int $schemeId)
    {
        $scheme = InstallmentScheme::with('customer')->findOrFail($schemeId);

        $alreadyPaidThisMonth = $scheme->payments()
            ->whereYear('paid_on', now()->year)
            ->whereMonth('paid_on', now()->month)
            ->exists();

        if ($alreadyPaidThisMonth) {
            return;
        }

        InstallmentPayment::create([
            'scheme_id' => $scheme->id,
            'amount' => $scheme->monthly_amount,
            'paid_on' => now()->toDateString(),
        ]);

        $scheme->increment('months_paid');
        $scheme->refresh();
        if ($scheme->is_matured) {
            $this->dispatch('toast', message: "{$scheme->customer->name}'s scheme has matured. Choose what happens next in Scheme List.", type: 'info');
        }
        $this->dispatch('toast', message: "Marked {$scheme->customer->name}'s installment as paid for ".now()->format('F Y').'.', type: 'success');
    }

    public function render()
    {
        $schemes = InstallmentScheme::with(['customer', 'payments' => function ($q) {
            $q->whereYear('paid_on', now()->year)->whereMonth('paid_on', now()->month);
        }])
            ->where('status', 'active')
            ->get()
            ->map(function ($scheme) {
                $scheme->paidThisMonth = $scheme->payments->isNotEmpty();
                return $scheme;
            });

        return view('livewire.installments.monthly-payment-status', [
            'schemes' => $schemes,
            'stats' => [
                'active' => $schemes->count(),
                'paid' => $schemes->where('paidThisMonth', true)->count(),
                'due' => $schemes->where('paidThisMonth', false)->count(),
                'dueAmount' => $schemes->where('paidThisMonth', false)->sum('monthly_amount'),
            ],
        ])->layout('components.layouts.app', ['title' => 'Monthly Payment Status — Radharani Jewellery ERP']);
    }
}
