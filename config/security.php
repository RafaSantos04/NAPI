<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security Lab
    |--------------------------------------------------------------------------
    |
    | The Security Lab runs deliberately vulnerable code against synthetic
    | data, side by side with the protected version (ADR-0010). It is off
    | unless explicitly enabled: when disabled its routes answer 404, its
    | menu entry is hidden and its use cases refuse to run.
    |
    */

    'lab' => [
        'enabled' => (bool) env('SECURITY_LAB_ENABLED', false),
    ],

];
