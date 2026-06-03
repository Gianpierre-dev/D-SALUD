<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Boletas internas
    |--------------------------------------------------------------------------
    | Serie usada para la numeración correlativa de las boletas internas.
    */
    'boleta' => [
        'serie' => env('DSALUD_BOLETA_SERIE', 'B001'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Comprobantes y tributos
    |--------------------------------------------------------------------------
    | Series por tipo de comprobante e IGV. En Perú el precio al público
    | INCLUYE IGV; el comprobante solo desglosa la base imponible y el impuesto.
    */
    'comprobante' => [
        'serie_boleta'  => env('DSALUD_SERIE_BOLETA', 'B001'),
        'serie_factura' => env('DSALUD_SERIE_FACTURA', 'F001'),
    ],
    'igv' => [
        // Tasa vigente del IGV en Perú (18%).
        'tasa' => (float) env('DSALUD_IGV_TASA', 0.18),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inventario
    |--------------------------------------------------------------------------
    | Parámetros para las alertas de stock y vencimiento.
    */
    'inventario' => [
        // Días de anticipación para alertar productos próximos a vencer.
        'dias_alerta_vencimiento' => (int) env('DSALUD_DIAS_ALERTA_VENCIMIENTO', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paginación
    |--------------------------------------------------------------------------
    */
    'paginacion' => [
        'por_pagina' => (int) env('DSALUD_PAGINACION_POR_PAGINA', 15),
    ],

];
