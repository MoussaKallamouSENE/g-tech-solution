@php
    $money = fn ($n) => number_format($n, 0, ',', "\u{00A0}").' FCFA';
    $labels = [
        'pending' => 'En attente de confirmation',
        'confirmed' => 'Confirmée',
        'preparing' => 'En préparation',
        'out_for_delivery' => 'En cours de livraison',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
    ];
    $paymentMethods = [
        'cash_on_delivery' => 'Paiement à la livraison',
    ];
    $warrantyTerms = [
        'Veuillez signaler tout problème constaté dans les 72 heures suivant la réception du matériel.',
        'La garantie commerciale couvre exclusivement la carte mère pendant 6 mois, dans les limites des conditions spécifiques indiquées pour le produit.',
        'Les réparations des autres composants peuvent être facturées si elles ne sont pas couvertes par une garantie applicable. Tout frais sera communiqué au client avant intervention.',
        'Ces conditions ne limitent ni ne remplacent les droits légaux du client.',
    ];
    $specLabels = [
        'reference' => 'Réf.',
        'brand' => 'Marque',
        'description' => 'Description',
        'processor' => 'Processeur',
        'ram' => 'RAM',
        'storage' => 'Stockage',
        'graphics_card' => 'Carte graphique',
        'screen' => 'Écran',
        'state' => 'État',
    ];
    $address = config('shop.address');
    $place = $address
        ? (str_contains($address, 'Dakar') ? $address : $address.', Dakar, Sénégal')
        : 'Dakar, Sénégal';
    $contact = collect([config('shop.phone'), config('shop.email')])->filter()->implode(' · ');
    $legal = collect([
        config('shop.ninea') ? 'NINEA : '.config('shop.ninea') : null,
        config('shop.rccm') ? 'RCCM : '.config('shop.rccm') : null,
    ])->filter()->implode(' · ');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Facture {{ $order->invoice_number }}</title>
<style>
  @page { margin: 0 0 58px 0; }
  body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.4; color: #0B1F3A; }
  table { border-collapse: collapse; }
  th, td { vertical-align: top; }
  tr { page-break-inside: avoid; }
  .specs { margin-top: 3px; font-size: 9px; line-height: 1.3; color: #5B6B82; }
  .warranty { font-size: 9px; line-height: 1.35; }
  .warranty li { margin: 2px 0; page-break-inside: avoid; }
  .warranty p { page-break-inside: avoid; }
</style>
</head>
<body>

<div style="position:fixed;left:0;right:0;bottom:-58px;height:58px">
  <div style="border-top:4px solid #2B4EFF;background:#F3F5FA;padding:11px 40px;height:32px">
    <table style="width:100%;font-size:11px;color:#5B6B82">
      <tr>
        <td style="font-weight:bold;color:#0B1F3A;font-size:12px">Merci de votre confiance.</td>
        <td style="text-align:right">{{ $legal ?: $place }}</td>
      </tr>
    </table>
  </div>
</div>

<table style="width:100%;background:#0B1F3A;color:#FFFFFF">
  <tr>
    <td style="padding:22px 40px;vertical-align:middle">
      <table>
        <tr>
          <td style="vertical-align:middle">
            <div style="width:40px;height:40px;background:#2B4EFF;color:#FFFFFF;text-align:center;line-height:40px;font-size:21px;font-weight:bold;border-radius:10px">G</div>
          </td>
          <td style="padding-left:12px;vertical-align:middle;font-size:23px;font-weight:bold">G-Tech</td>
        </tr>
      </table>
      <div style="margin-top:10px;font-size:10px;color:#C3CCDC;line-height:1.5">
        Matériel informatique et maintenance<br>
        {{ $place }}
        @if ($contact)<br>{{ $contact }}@endif
      </div>
    </td>
    <td style="padding:22px 40px;text-align:right;vertical-align:middle">
      <div style="font-size:32px;font-weight:bold;letter-spacing:3px">FACTURE</div>
      <div style="margin-top:8px;font-size:15px;font-weight:bold">N° FA-{{ substr($order->number, 3) }}</div>
      <div style="margin-top:4px;font-size:12px;color:#C3CCDC">Date : {{ $order->created_at->format('d/m/Y') }}</div>
      <div style="font-size:12px;color:#C3CCDC">Commande : {{ $order->number }}</div>
      <div style="font-size:12px;color:#C3CCDC">Facture : {{ $order->invoice_number }}</div>
    </td>
  </tr>
</table>

<div style="padding:20px 40px 0">
  <table style="width:100%">
    <tr>
      <td style="width:50%;padding-right:20px">
        <div style="font-size:12px;font-weight:bold;color:#1E3AD1;margin-bottom:6px">Facturé à</div>
        <div style="font-weight:bold;font-size:14px">{{ $order->customer_name }}</div>
        <div>{{ $order->customer_phone }}</div>
        <div>{!! nl2br(e($order->customer_address)) !!}</div>
        @if ($order->zone)<div style="color:#5B6B82">Zone : {{ $order->zone->name }}</div>@endif
      </td>
      <td style="width:50%">
        <div style="font-size:12px;font-weight:bold;color:#1E3AD1;margin-bottom:6px">Paiement</div>
        <div style="font-weight:bold;font-size:14px">{{ $paymentMethods[$order->payment_method] ?? $order->payment_method }}</div>
        @if ($order->payment_method === 'cash_on_delivery')<div>Règlement en espèces à la réception</div>@endif
        <div style="color:#5B6B82">Statut : {{ $labels[$order->status] ?? $order->status }}</div>
      </td>
    </tr>
  </table>

  <table style="width:100%;margin-top:14px">
    <thead>
      <tr style="background:#0B1F3A;color:#FFFFFF">
        <th style="text-align:left;padding:9px 12px;font-size:11px">Désignation et caractéristiques</th>
        <th style="text-align:right;padding:9px 12px;font-size:11px;width:40px">Qté</th>
        <th style="text-align:right;padding:9px 12px;font-size:11px;width:105px">Prix unitaire</th>
        <th style="text-align:right;padding:9px 12px;font-size:11px;width:110px">Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($order->items as $item)
      @php
        $hasSalesSnapshot = $item->product_specs_snapshot !== null;
        $specifications = $item->product_specs_snapshot ?? [];
        if (! $hasSalesSnapshot && $item->product) {
            $specifications = array_filter([
                'reference' => $item->product->reference,
                'brand' => $item->product->brand,
                'description' => $item->product->description,
                'processor' => $item->product->processor,
                'ram' => $item->product->ram,
                'storage' => $item->product->storage,
                'graphics_card' => $item->product->graphics_card,
                'screen' => $item->product->screen,
                'state' => $item->product->state,
            ], fn ($value) => $value !== null && $value !== '');
        }
      @endphp
      <tr style="background:{{ $loop->even ? '#F3F5FA' : '#FFFFFF' }}">
        <td style="padding:10px 12px;border-bottom:1px solid #E1E6EF">
          <div style="font-weight:bold">{{ $item->product_name }}</div>
          @if (! $hasSalesSnapshot && $specifications)
            <div class="specs"><strong>Caractéristiques actuelles — non archivées lors de la vente :</strong></div>
          @endif
          @if ($specifications)
            <div class="specs">
              @foreach ($specifications as $key => $value)
                {{ $specLabels[$key] ?? $key }} : {{ $value }}@if (! $loop->last) · @endif
              @endforeach
            </div>
          @endif
        </td>
        <td style="padding:10px 12px;border-bottom:1px solid #E1E6EF;text-align:right">{{ $item->quantity }}</td>
        <td style="padding:10px 12px;border-bottom:1px solid #E1E6EF;text-align:right">{{ $money($item->unit_price) }}</td>
        <td style="padding:10px 12px;border-bottom:1px solid #E1E6EF;text-align:right;font-weight:bold">{{ $money($item->total) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <table style="width:100%;margin-top:14px">
    <tr>
      <td style="width:52%"></td>
      <td>
        <table style="width:100%">
          <tr><td style="padding:4px 12px;color:#5B6B82">Sous-total</td><td style="padding:6px 14px;text-align:right">{{ $money($order->subtotal) }}</td></tr>
          <tr><td style="padding:4px 12px;color:#5B6B82">Livraison</td><td style="padding:6px 14px;text-align:right">{{ $money($order->delivery_fee) }}</td></tr>
        </table>
        <div style="background:#2B4EFF;color:#FFFFFF;border-radius:10px;padding:11px 12px;margin-top:6px">
          <table style="width:100%">
            <tr>
              <td style="font-weight:bold;font-size:14px;color:#FFFFFF">Total à payer</td>
              <td style="text-align:right;font-weight:bold;font-size:16px;color:#FFFFFF">{{ $money($order->total) }}</td>
            </tr>
          </table>
        </div>
      </td>
    </tr>
  </table>

  <table style="width:100%;margin-top:14px">
    <tr>
      <td style="width:44%;padding-right:16px;vertical-align:top">
        <div style="background:#F3F5FA;border-left:3px solid #2B4EFF;border-radius:8px;padding:10px 12px">
          <div style="font-size:11px;font-weight:bold;color:#1E3AD1;margin-bottom:4px">État du paiement</div>
          <div>Montant payé : non enregistré dans le système</div>
          <div>Reste à payer : non calculable (paiement non suivi)</div>
          <div style="margin-top:4px">Les paiements et montants encaissés ne sont pas suivis par le système. Cette facture ne constitue pas un reçu de paiement.</div>
        </div>
      </td>
      <td style="width:56%;vertical-align:top">
        <div class="warranty">
          <div style="font-size:11px;font-weight:bold;color:#1E3AD1;margin-bottom:4px">Garantie et conditions commerciales</div>
          <ul style="margin:0;padding-left:16px">
            @foreach ($warrantyTerms as $term)
              <li>{{ $term }}</li>
            @endforeach
          </ul>
          @foreach ($order->items as $item)
            @if ($item->warranty_conditions_snapshot)
              <p style="margin-top:6px"><strong>{{ $item->product_name }} — conditions spécifiques applicables à la vente :</strong><br>{{ $item->warranty_conditions_snapshot }}</p>
            @endif
          @endforeach
          @if ($order->items->contains(fn ($item) => ! $item->warranty_conditions_snapshot))
            <p style="margin-top:5px;color:#5B6B82">Aucune condition commerciale spécifique supplémentaire n’a été enregistrée pour certains produits au moment de la vente.</p>
          @endif
        </div>
      </td>
    </tr>
  </table>
</div>

</body>
</html>
