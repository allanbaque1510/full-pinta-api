<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mensajes de autenticación
    |--------------------------------------------------------------------------
    |
    | FullPinta no autentica por contraseña (§4.3, solo OTP), así que en la
    | práctica solo `throttle` puede llegar a mostrarse (Sanctum/rate limiting
    | genérico). Se traduce igual el archivo completo para no dejar un hueco
    | en inglés si algo del framework lo usa.
    |
    */

    'failed' => 'Esas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña ingresada es incorrecta.',
    'throttle' => 'Demasiados intentos. Intenta de nuevo en :seconds segundos.',

];
