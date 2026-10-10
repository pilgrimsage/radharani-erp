<?php
namespace App\Support;

use App\Models\Customer\Customer;
use App\Models\Customer\InstallmentScheme;
use App\Models\Notification\PendingNotification;
use App\Models\Orders\Order;
use App\Models\Sales\Sale;
use Illuminate\Support\Facades\Auth;

/**
 * Every customer message in one place (8 Oct change list, 17.1). Messages are only
 * generated here, copied by staff into WhatsApp or SMS, then marked sent: nothing is
 * ever sent automatically.
 */
class MessageTemplates
{
    /** group => type => label */
    public const GROUPS = [
        'Sales' => ['sale_confirmation' => 'Sale confirmation'],
        'Installment' => [
            'scheme_welcome' => 'New scheme welcome',
            'installment_reminder' => 'Reminder',
            'scheme_default' => 'Scheme defaulted',
            'scheme_completed' => 'Scheme completed',
        ],
        'Order' => [
            'order_accepted' => 'Order accepted',
            'order_from_karigar' => 'Received from karigar',
            'order_to_hallmarking' => 'Sent to hallmarking',
            'order_ready' => 'Ready to collect',
        ],
        'Other' => ['loyalty_award' => 'Points awarded', 'exchange_valuation_ready' => 'Exchange valuation ready', 'other' => 'Other'],
    ];

    public static function label(string $type): string
    {
        foreach (self::GROUPS as $types) {
            if (isset($types[$type])) {
                return $types[$type];
            }
        }

        return ucfirst(str_replace('_', ' ', $type));
    }

    public static function groupOf(string $type): string
    {
        foreach (self::GROUPS as $group => $types) {
            if (isset($types[$type])) {
                return $group;
            }
        }

        return 'Other';
    }

    /** "10,000 cash and 10,000 UPI" from a sale's payment parts. */
    public static function paymentText(?array $modes): string
    {
        $names = ['cash' => 'cash', 'upi' => 'UPI', 'card' => 'card', 'bank' => 'bank transfer'];
        $parts = collect($modes ?? [])->filter(fn ($m) => (float) ($m['amount'] ?? 0) > 0)
            ->map(fn ($m) => number_format((float) $m['amount']) . ' ' . ($names[$m['mode'] ?? ''] ?? ($m['mode'] ?? '')))->values();

        if ($parts->isEmpty()) {
            return '';
        }

        return $parts->count() === 1 ? $parts->first() : $parts->slice(0, -1)->implode(', ') . ' and ' . $parts->last();
    }

    // ---------------------------------------------------------------- builders

    public static function saleConfirmation(Sale $sale): string
    {
        $paid = self::paymentText($sale->payment_modes);
        $review = config('shop.review_link');

        return "Thank you for your purchase from Radharani Jewellery Works. Your bill of ₹" . number_format((float) $sale->total) . ' is confirmed'
            . ($paid ? ", received as {$paid}." : '.')
            . ($review ? " We would love your review: {$review}" : '');
    }

    public static function orderAccepted(Order $order): string
    {
        $when = $order->expected_ready_date ? ' It should be ready by ' . $order->expected_ready_date->format('j M Y') . '.' : '';

        return "We have accepted your order for {$order->product_description}.{$when} We will message you when it is ready.";
    }

    public static function orderFromKarigar(Order $order): string
    {
        return "Your {$order->product_description} is back from the karigar. We will update you at the next step.";
    }

    public static function orderToHallmarking(Order $order): string
    {
        return "Your {$order->product_description} has gone for hallmarking. We will message you when it is ready.";
    }

    public static function orderReady(Order $order): string
    {
        return "Your {$order->product_description} is ready and at the store. Please collect it when you can.";
    }

    public static function schemeWelcome(InstallmentScheme $s): string
    {
        return 'Welcome to the Radharani monthly scheme. Your instalment is ₹' . number_format((float) $s->monthly_amount) . ' a month, starting ' . $s->start_date->format('j M Y') . '.';
    }

    public static function schemeReminder(InstallmentScheme $s): string
    {
        return 'A reminder that your monthly instalment of ₹' . number_format((float) $s->monthly_amount) . ' is due.';
    }

    public static function schemeDefault(InstallmentScheme $s): string
    {
        return 'Your monthly scheme has missed payments and is now marked as defaulted. Please speak to the shop about what happens next.';
    }

    public static function schemeCompleted(InstallmentScheme $s): string
    {
        return 'Congratulations, your monthly scheme is complete. Please visit the shop to choose how to use it.';
    }

    // ---------------------------------------------------------------- queueing

    public static function queue(string $type, ?Customer $customer, string $message, ?string $relatedType = null, ?int $relatedId = null): PendingNotification
    {
        return PendingNotification::create([
            'customer_id' => $customer?->id,
            'type' => $type,
            'recipient_name' => $customer?->name,
            'recipient_phone' => $customer?->phone,
            'message' => $message,
            'status' => 'pending',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'created_by' => Auth::id(),
        ]);
    }
}
