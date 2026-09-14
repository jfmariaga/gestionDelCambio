<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Single Sign-On (Microsoft Entra ID)
    |--------------------------------------------------------------------------
    | Fase 1: solo el ingreso. El login local con correo/contraseña se conserva.
    */

    // Interruptor global. Apagado ⇒ el botón no aparece y /auth/microsoft/* da 404.
    'enabled' => (bool) env('SSO_ENABLED', false),

    // Rol spatie que recibe el usuario creado al vuelo (JIT) en su primer ingreso.
    'default_role' => env('SSO_DEFAULT_ROLE', 'consulta'),

    // Lista opcional de dominios de correo permitidos (coma-separados). Vacío = cualquier
    // cuenta del tenant configurado en Azure.
    'allowed_domains' => array_filter(array_map(
        'trim',
        explode(',', (string) env('SSO_ALLOWED_DOMAINS', '')),
    )),

    // Fase 2: al salir, cerrar también la sesión en Entra ID.
    'logout_from_idp' => (bool) env('SSO_LOGOUT_FROM_IDP', false),

    // Texto del botón en la pantalla de login.
    'button_label' => env('SSO_BUTTON_LABEL', 'Continuar con Microsoft'),
];
