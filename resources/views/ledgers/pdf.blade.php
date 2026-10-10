<!doctype html>
<html lang="en"><head><meta charset="utf-8"><style>
 body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }
 h1 { font-size: 16px; margin: 0 0 2px; } .sub { color: #666; margin-bottom: 12px; }
 table { width: 100%; border-collapse: collapse; } th, td { border-bottom: 1px solid #ccc; padding: 5px 6px; text-align: left; }
 th { background: #f1ede4; } td.n, th.n { text-align: right; } tr.total td { font-weight: bold; background: #f7f5f0; }
</style></head><body>
@php $fmt = fn ($v) => $v === null ? '' : (is_float($v) ? number_format($v, 3) : $v); @endphp
<h1>{{ $party->name }}</h1>
<div class="sub">{{ ['karigar' => 'Karigar', 'hallmark_center' => 'Hallmarking centre'][$party->type ?? ''] ?? 'Customer' }} ledger · Radharani Jewellery Works · {{ now()->format('j M Y, g:i a') }}</div>
<table>
  <thead><tr>@foreach ($ledger['columns'] as $i => $c)<th class="{{ $i > 1 ? 'n' : '' }}">{{ $c }}</th>@endforeach</tr></thead>
  <tbody>
    @foreach ($ledger['rows'] as $r)
      <tr><td>{{ $r['at']->format('j M Y, g:i a') }}</td><td>{{ $r['label'] }}</td>@foreach ($r['cells'] as $c)<td class="n">{{ $fmt($c) }}</td>@endforeach</tr>
    @endforeach
    <tr class="total"><td colspan="2">Total</td>@foreach ($ledger['totals'] as $t)<td class="n">{{ $fmt($t) }}</td>@endforeach</tr>
  </tbody>
</table>
</body></html>
