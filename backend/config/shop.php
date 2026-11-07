<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Coordonnées de la boutique
    |--------------------------------------------------------------------------
    |
    | Informations affichées sur la facture PDF. Les valeurs vides ne sont pas
    | imprimées : renseignez-les pour faire apparaître l'adresse, le contact
    | et les mentions légales sur les documents de vente.
    |
    */

    'address' => env('SHOP_ADDRESS', ''),

    'phone' => env('SHOP_PHONE', ''),

    'email' => env('SHOP_EMAIL', ''),

    'ninea' => env('SHOP_NINEA', ''),

    'rccm' => env('SHOP_RCCM', ''),

];
