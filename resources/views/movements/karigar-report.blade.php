<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Karigar issue · {{ $batch->vendor->name }} · {{ $batch->label }}</title>
<style>
  body { font: 14px/1.5 system-ui, sans-serif; color: #1a1a1a; margin: 0; padding: 32px; max-width: 760px; margin-inline: auto; }
  h1 { font-size: 22px; margin: 0 0 4px; }
  .sub { color: #6f6b63; margin-bottom: 24px; }
  table { width: 100%; border-collapse: collapse; margin: 16px 0; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #ddd; vertical-align: top; }
  th { width: 34%; color: #6f6b63; font-weight: 600; }
  .sign { display: flex; gap: 48px; margin-top: 64px; }
  .sign div { flex: 1; border-top: 1px solid #1a1a1a; padding-top: 6px; color: #6f6b63; }
  .actions { margin-bottom: 20px; }
  button { font: inherit; padding: 8px 16px; cursor: pointer; }
  @media print { .actions { display: none; } body { padding: 0; } }
</style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Print</button></div>
<h1>Issued to karigar</h1>
<div class="sub">Radharani Jewellery Works · {{ $batch->label }}</div>
<table>
  <tr><th>Karigar</th><td>{{ $batch->vendor->name }}{{ $batch->vendor->phone ? ' · ' . $batch->vendor->phone : '' }}</td></tr>
  <tr><th>Description</th><td>{{ $batch->description ?: $batch->purpose_label }}</td></tr>
  @if ($batch->categories)<tr><th>Categories</th><td>{{ implode(', ', $batch->categories) }}</td></tr>@endif
  <tr><th>Metal</th><td>{{ ucfirst($batch->metal) }}</td></tr>
  <tr><th>Pieces expected</th><td>{{ $batch->pieces_expected }}</td></tr>
  <tr><th>Estimated weight</th><td>{{ number_format($batch->weight_out, 3) }} g</td></tr>
  <tr><th>Expected back</th><td>{{ $batch->expected_return?->format('j M Y') ?? '-' }}</td></tr>
  <tr><th>Advance</th><td>
    @if ($batch->advance_cash > 0 || $batch->advance_metal_weight > 0)
      @if ($batch->advance_cash > 0)Cash ₹{{ number_format($batch->advance_cash) }}@endif
      @if ($batch->advance_cash > 0 && $batch->advance_metal_weight > 0) and @endif
      @if ($batch->advance_metal_weight > 0){{ number_format($batch->advance_metal_weight, 3) }} g {{ $batch->metal }} {{ $batch->advance_metal_purity }}@endif
    @else No advance @endif
  </td></tr>
  @if ($batch->order_id)<tr><th>For order</th><td>#{{ $batch->order_id }}</td></tr>@endif
  @if ($batch->note)<tr><th>Comments</th><td>{{ $batch->note }}</td></tr>@endif
  <tr><th>Issued by</th><td>{{ $batch->user?->name }}</td></tr>
</table>
<div class="sign"><div>Karigar signature</div><div>Shop signature</div></div>
</body>
</html>
