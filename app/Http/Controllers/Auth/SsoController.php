<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\ConflictoProveedorException;
use App\Domain\Auth\ProvisionarUsuarioSso;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

/**
 * Ingreso por Single Sign-On con Microsoft Entra ID (Fase 1). Convive con el login local.
 */
class SsoController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(config('sso.enabled'), 404);

        return Socialite::driver('azure')->redirect();
    }

    public function callback(Request $request, ProvisionarUsuarioSso $provisionar): RedirectResponse
    {
        abort_unless(config('sso.enabled'), 404);

        if ($request->filled('error')) {
            return $this->fallo($request->input('error') === 'access_denied' ? 'cancelado' : 'generico');
        }

        try {
            $sso = Socialite::driver('azure')->user();
        } catch (InvalidStateException) {
            return $this->fallo('estado');
        } catch (Throwable $e) {
            report($e);

            return $this->fallo('generico');
        }

        $email = $sso->getEmail()
            ?: ($sso->user['upn'] ?? $sso->user['preferred_username'] ?? null);

        if (blank($email)) {
            return $this->fallo('sin_correo');
        }

        if (! $this->dominioPermitido($email)) {
            return $this->fallo('dominio');
        }

        try {
            $usuario = $provisionar($sso, $email);
        } catch (ConflictoProveedorException) {
            return $this->fallo('conflicto');
        }

        Auth::login($usuario, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function dominioPermitido(string $email): bool
    {
        $permitidos = config('sso.allowed_domains');

        if (empty($permitidos)) {
            return true;
        }

        $dominio = strtolower(substr(strrchr($email, '@') ?: '', 1));

        return in_array($dominio, array_map('strtolower', $permitidos), true);
    }

    private function fallo(string $clave): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'email' => __("sso.errores.{$clave}"),
        ]);
    }
}
