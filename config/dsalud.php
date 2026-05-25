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
