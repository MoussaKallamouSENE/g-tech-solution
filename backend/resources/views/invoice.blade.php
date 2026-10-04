@php
    $money = fn ($n) => number_format($n, 0, ',', "\u{00A0}").' FCFA';
    $labels = [
        'pending' => 'En attente de confirmation',
        'confirmed' => 'Confirmée',
        'preparing' => 'En préparation',
        'out_for_delivery' => 'En cours de livraison',
        'delivered' => 'Livrée',
    ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Facture FA-{{ substr($order->number, 3) }}</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0B1F3A; }
  h1 { font-size: 24px; margin: 0; }
  h2 { font-size: 11px; margin: 0 0 6px 0; color: #6b7a90; text-transform: uppercase; }
  .muted { color: #6b7a90; }
  .right { text-align: right; }
  .brand { font-size: 22px; font-weight: bold; }
  .brand span { color: #2B4EFF; }
  table { width: 100%; border-collapse: collapse; }
  .items { margin-top: 28px; }
  .items th { text-align: left; border-bottom: 2px solid #0B1F3A; padding: 8px 6px; font-size: 10px; }
  .items th.right { text-align: right; }
  .items td { border-bottom: 1px solid #E1E6EF; padding: 8px 6px; }
  .totals { width: 45%; margin-left: 55%; margin-top: 16px; }
  .totals td { padding: 4px 6px; }
  .grand td { border-top: 2px solid #0B1F3A; font-size: 14px; font-weight: bold; padding-top: 8px; }
  .footer { margin-top: 40px; padding-top: 10px; border-top: 1px solid #E1E6EF; text-align: center; font-size: 10px; }
</style>
</head>
<body>
  <table>
    <tr>
      <td>
        <div class="brand">G-<span>Tech</span></div>
        <div class="muted">Matériel informatique et maintenance<br>Dakar, Sénégal</div>
      </td>
      <td class="right">
        <h1>FACTURE</h1>
        <div>N° FA-{{ substr($order->number, 3) }}</div>
        <div class="muted">Date : {{ $order->created_at->format('d/m/Y') }}</div>
        <div class="muted">Commande : {{ $order->number }}</div>
      </td>
    </tr>
  </table>

  <table style="margin-top: 28px;">
    <tr>
      <td style="width: 50%; vertical-align: top;">
        <h2>Facturé à</h2>
        <strong>{{ $order->customer_name }}</strong><br>
        {{ $order->customer_phone }}<br>
        {!! nl2br(e($order->customer_address)) !!}
        @if ($order->zone)<br><span class="muted">Zone : {{ $order->zone->name }}</span>@endif
      </td>
      <td style="width: 50%; vertical-align: top;">
        <h2>Paiement</h2>
        Paiement à la livraison<br>
        <span class="muted">Statut de la commande : {{ $labels[$order->status] ?? $order->status }}</span>
      </td>
    </tr>
  </table>

  <table class="items">
    <thead>
      <tr>
        <th>Désignation</th>
        <th class="right">Qté</th>
        <th class="right">Prix unitaire</th>
        <th class="right">Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($order->items as $item)
      <tr>
        <td>{{ $item->product_name }}</td>
        <td class="right">{{ $item->quantity }}</td>
        <td class="right">{{ $money($item->unit_price) }}</td>
        <td class="right">{{ $money($item->total) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <table class="totals">
    <tr><td>Sous-total</td><td class="right">{{ $money($order->subtotal) }}</td></tr>
    <tr><td>Livraison</td><td class="right">{{ $money($order->delivery_fee) }}</td></tr>
    <tr class="grand"><td>Total à payer</td><td class="right">{{ $money($order->total) }}</td></tr>
  </table>

  <div class="footer">Merci de votre confiance. G-Tech, Dakar, Sénégal.</div>
</body>
</html>