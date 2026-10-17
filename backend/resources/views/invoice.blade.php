@php
    $money = fn ($amount) => number_format((int) $amount, 0, ',', "\u{00A0}").' FCFA';
    $orderStatuses = [
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
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $order->invoice_number }}</title>
    <style>
        @page { margin: 30px 38px 48px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; color: #17213b; }
        h1, h2, p { margin: 0; }
        .header { width: 100%; border-bottom: 3px solid #3548c5; padding-bottom: 18px; }
        .header td { vertical-align: top; }
        .brand-mark { display: inline-block; width: 34px; height: 34px; line-height: 34px; text-align: center; border-radius: 9px; background: #3548c5; color: #fff; font-size: 22px; font-weight: bold; }
        .brand-name { display: inline-block; margin-left: 7px; vertical-align: top; padding-top: 4px; font-size: 18px; font-weight: bold; color: #17213b; }
        .brand-name span { color: #7253bd; }
        .company { margin-top: 8px; color: #65708a; font-size: 9px; }
        .document-title { text-align: right; }
        .document-title h1 { font-size: 24px; letter-spacing: 1px; color: #3548c5; }
        .invoice-number { margin-top: 4px; font-size: 11px; font-weight: bold; }
        .muted { color: #65708a; }
        .details { width: 100%; margin-top: 22px; }
        .details td { width: 50%; vertical-align: top; padding: 0 12px 0 0; }
        .details td + td { padding: 0 0 0 12px; }
        .section-title { margin-bottom: 7px; color: #7253bd; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .7px; }
        .items { width: 100%; margin-top: 24px; border-collapse: collapse; }
        .items thead { display: table-header-group; }
        .items th { padding: 8px 7px; background: #3548c5; color: #fff; text-align: left; font-size: 9px; }
        .items th.right, .items td.right { text-align: right; }
        .items td { padding: 9px 7px; border-bottom: 1px solid #e2e5ef; vertical-align: top; }
        .items tr { page-break-inside: avoid; }
        .item-name { font-weight: bold; }
        .specs { margin-top: 3px; color: #65708a; font-size: 8px; }
        .totals { width: 47%; margin: 15px 0 0 auto; border-collapse: collapse; }
        .totals td { padding: 5px 7px; }
        .totals .grand td { border-top: 2px solid #3548c5; padding-top: 9px; color: #17213b; font-size: 13px; font-weight: bold; }
        .payment { margin-top: 20px; padding: 12px; border: 1px solid #e2e5ef; border-left: 3px solid #7253bd; border-radius: 4px; }
        .warranty { margin-top: 20px; page-break-inside: avoid; }
        .warranty ul { margin: 6px 0 0; padding-left: 16px; }
        .warranty li { margin: 3px 0; }
        .footer { position: fixed; right: 0; bottom: -30px; left: 0; padding-top: 8px; border-top: 1px solid #e2e5ef; color: #65708a; text-align: center; font-size: 8px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <span class="brand-mark">G</span><span class="brand-name">G-Tech <span>Solution</span></span>
                <div class="company">
                    {{ $company['name'] }}<br>
                    {{ $company['address'] }}
                    @if ($company['phone'])<br>Tél. : {{ $company['phone'] }}@endif
                    @if ($company['email'])<br>{{ $company['email'] }}@endif
                    @if ($company['website'])<br>{{ $company['website'] }}@endif
                </div>
            </td>
            <td class="document-title">
                <h1>FACTURE</h1>
                <div class="invoice-number">{{ $order->invoice_number }}</div>
                <div class="muted">Émise le {{ $order->created_at->format('d/m/Y') }}</div>
                <div class="muted">Commande {{ $order->number }}</div>
            </td>
        </tr>
    </table>

    <table class="details">
        <tr>
            <td>
                <div class="section-title">Facturé à</div>
                <strong>{{ $order->customer_name }}</strong><br>
                {{ $order->customer_phone }}<br>
                {!! nl2br(e($order->customer_address)) !!}
                @if ($order->zone)<br><span class="muted">Zone : {{ $order->zone->name }}</span>@endif
            </td>
            <td>
                <div class="section-title">Commande et paiement</div>
                <div>Statut de la commande : <strong>{{ $orderStatuses[$order->status] ?? $order->status }}</strong></div>
                <div>Mode : {{ $paymentMethods[$order->payment_method] ?? $order->payment_method }}</div>
                <div>Montant payé : non enregistré dans le système</div>
                <div>Reste à payer : non calculable (paiement non suivi)</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 48%">Désignation et caractéristiques</th>
                <th class="right" style="width: 8%">Qté</th>
                <th class="right" style="width: 20%">Prix unitaire</th>
                <th class="right" style="width: 24%">Sous-total</th>
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
                <tr>
                    <td>
                        <div class="item-name">{{ $item->product_name }}</div>
                        @if (! $hasSalesSnapshot && $specifications)
                            <div class="specs"><strong>Caractéristiques actuelles — non archivées lors de la vente :</strong></div>
                        @endif
                        @if ($specifications)
                            <div class="specs">
                                @foreach ($specifications as $key => $value)
                                    {{ ['reference' => 'Réf.', 'brand' => 'Marque', 'description' => 'Description', 'processor' => 'Processeur', 'ram' => 'RAM', 'storage' => 'Stockage', 'graphics_card' => 'Carte graphique', 'screen' => 'Écran', 'state' => 'État'][$key] ?? $key }} : {{ $value }}@if (! $loop->last) · @endif
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ $money($item->unit_price) }}</td>
                    <td class="right">{{ $money($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Sous-total produits</td><td class="right">{{ $money($order->subtotal) }}</td></tr>
        <tr><td>Frais de livraison</td><td class="right">{{ $money($order->delivery_fee) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="right">{{ $money($order->total) }}</td></tr>
    </table>

    <section class="payment">
        <div class="section-title">État du paiement</div>
        Les paiements et montants encaissés ne sont pas suivis par le système. Cette facture ne constitue pas un reçu de paiement.
    </section>

    <section class="warranty">
        <div class="section-title">Garantie et conditions commerciales</div>
        <ul>
            @foreach ($warrantyTerms as $term)
                <li>{{ $term }}</li>
            @endforeach
        </ul>
        @foreach ($order->items as $item)
            @if ($item->warranty_conditions_snapshot)
                <p style="margin-top: 8px"><strong>{{ $item->product_name }} — conditions spécifiques applicables à la vente :</strong><br>{{ $item->warranty_conditions_snapshot }}</p>
            @endif
        @endforeach
        @if ($order->items->contains(fn ($item) => ! $item->warranty_conditions_snapshot))
            <p class="muted" style="margin-top: 7px">Aucune condition commerciale spécifique supplémentaire n’a été enregistrée pour certains produits au moment de la vente.</p>
        @endif
    </section>

    <div class="footer">Merci de votre confiance · {{ $company['name'] }} · {{ $company['address'] }}</div>
</body>
</html>
