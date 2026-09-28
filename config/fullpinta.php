<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP
    |--------------------------------------------------------------------------
    |
    | La especificación no fija estos números (no hay tabla `otp` en el §4);
    | son una decisión de implementación razonable, no una cita textual del
    | documento. Ajustar aquí si el producto pide otra cosa.
    |
    */

    'otp' => [
        'digitos' => 6,
        'expira_minutos' => 5,
        'max_intentos_verificacion' => 5,
        'max_solicitudes_por_ventana' => 3,
        'ventana_minutos' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Login por correo/contraseña
    |--------------------------------------------------------------------------
    |
    | Mismo criterio que el OTP: protege de fuerza bruta sobre la contraseña.
    |
    */

    'login' => [
        'max_intentos_por_ventana' => 5,
        'ventana_minutos' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sesiones simultáneas
    |--------------------------------------------------------------------------
    |
    | Decisión de producto, no de la especificación: cuántos dispositivos con
    | sesión activa puede tener un mismo usuario a la vez. Al iniciar sesión
    | por encima del límite, se cierra el más antiguo — nunca se rechaza el
    | login nuevo. En 2 porque propietario/recepción suelen necesitar su
    | teléfono personal Y la tablet del mostrador abiertos a la vez.
    |
    */

    'max_dispositivos_activos' => env('FULLPINTA_MAX_DISPOSITIVOS_ACTIVOS', 2),

    /*
    |--------------------------------------------------------------------------
    | Vigencia de la suscripción
    |--------------------------------------------------------------------------
    |
    | Margen de gracia confirmado con el usuario: 10 días desde que se cumple
    | el período pagado y no hay confirmación de pago, antes de bajar el
    | negocio a Free. Decisión de implementación del equipo técnico, no varía
    | por plan ni por negocio — mismo criterio que `otp`/`login`.
    |
    */

    'suscripcion' => [
        'dias_gracia' => env('FULLPINTA_DIAS_GRACIA_SUSCRIPCION', 10),
    ],

];
