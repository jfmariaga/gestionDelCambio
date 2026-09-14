<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Azure\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Acceso a la sección de administración del portal.
        Gate::define('admin', fn ($user) => $user->hasRole('administrador'));

        // Proveedor de Socialite para Microsoft Entra ID (SSO). No hay EventServiceProvider
        // en este esqueleto (estilo Laravel 11+), así que se registra aquí.
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('azure', Provider::class);
        });
    }
}
