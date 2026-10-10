<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bill · {{ $sale->bill_number }}</title>
<style>
 body { font: 14px/1.5 system-ui, sans-serif; color: #1a1a1a; margin: 0; padding: 32px; max-width: 700px; margin-inline: auto; }
 h1 { font-size: 22px; margin: 0; } .sub { color: #6f6b63; margin-bottom: 20px; }
 table { width: 100%; border-collapse: collapse; margin: 14px 0; } th, td { text-align: left; padding: 7px 8px; border-bottom: 1px solid #ddd; }
 td.n, th.n { text-align: right; } tr.total td { font-weight: 700; border-top: 2px solid #1a1a1a; }
 .actions { margin-bottom: 18px; } button { font: inherit; padding: 8px 16px; cursor: pointer; }
 @media print { .actions { display: none; } body { padding: 0; } }
</style></head><body>
<div class="actions"><button onclick="window.print()">Print</button></div>
<h1>Radharani Jewellery Works</h1>
<div class="sub">{{ $sale->created_at->format('j M Y, g:i a') }} · {{ $sale->confirmed_by_accountant ? 'Bill ' . $sale->invoice_number : 'Not yet verified' }}</div>
<div><strong>{{ $sale->customer->name }}</strong> · {{ $sale->customer->phone }}</div>
<table>
  <thead><tr><th>Item</th><th>Weight</th><th class="n">Price</th></tr></thead>
  <tbody>
    @foreach ($sale->items as $item)
      <tr><td>{{ $item->label }} · {{ $item->category }}</td><td>{{ number_format($item->weight, 3) }} g</td><td class="n">₹{{ number_format($item->pivot->price_at_sale) }}</td></tr>
    @endforeach
    @foreach ($sale->additional_charges ?? [] as $c)<tr><td colspan="2">{{ $c['name'] }}</td><td class="n">₹{{ number_format($c['amount'], 2) }}</td></tr>@endforeach
    @if ($sale->discount > 0)<tr><td colspan="2">Adjustment</td><td class="n">- ₹{{ number_format($sale->discount, 2) }}</td></tr>@endif
    <tr class="total"><td colspan="2">Total ({{ $sale->items->count() }} {{ \Illuminate\Support\Str::plural('item', $sale->items->count()) }})</td><td class="n">₹{{ number_format($sale->total) }}</td></tr>
  </tbody>
</table>
<table>
  <tbody>
    @foreach ($sale->payments as $p)<tr><td>Paid by {{ \App\Models\Sales\SalePayment::MODES[$p->mode] }} · {{ $p->created_at->format('j M Y') }}</td><td class="n">₹{{ number_format($p->amount, 2) }}</td></tr>@endforeach
    <tr class="total"><td>Balance</td><td class="n">₹{{ number_format($sale->balance, 2) }}</td></tr>
  </tbody>
</table>
<p style="color:#6f6b63;font-size:12px">Thank you.</p>
</body></html>
