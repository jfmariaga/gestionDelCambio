<?php

namespace App\Domain\Auth;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Resuelve el usuario local a partir de la identidad que devuelve el IdP (Entra ID):
 *
 *   1. Por (provider, provider_id) → devuelve el usuario ya vinculado.
 *   2. Por correo → vincula la cuenta local existente (que no tenga otro proveedor).
 *   3. Si no existe → lo crea al vuelo (JIT) con el rol por defecto de `config('sso.default_role')`.
 *
 * El login local con contraseña se conserva; a los usuarios SSO se les guarda una contraseña
 * aleatoria (la columna es NOT NULL) que además les permite usar "olvidé mi contraseña".
 */
final class ProvisionarUsuarioSso
{
    public function __invoke(SocialiteUser $sso, string $email): User
    {
        $provider = 'azure';
        $providerId = (string) $sso->getId();
        $nombre = $sso->getName() ?: $sso->getNickname() ?: $email;

        return DB::transaction(function () use ($provider, $providerId, $email, $nombre) {
            $porIdentidad = User::query()
                ->where('provider', $provider)
                ->where('provider_id', $providerId)
                ->first();

            if ($porIdentidad) {
                $this->refrescar($porIdentidad, $nombre);

                return $porIdentidad;
            }

            $porCorreo = User::query()
                ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
                ->first();

            if ($porCorreo) {
                if ($porCorreo->provider !== null && $porCorreo->provider_id !== $providerId) {
                    throw new ConflictoProveedorException("El correo {$email} ya está vinculado a otra identidad.");
                }

                $porCorreo->forceFill([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'email_verified_at' => $porCorreo->email_verified_at ?? now(),
                ])->save();

                return $porCorreo;
            }

            try {
                $usuario = User::create([
                    'name' => $nombre,
                    'email' => $email,
                    'password' => Hash::make(Str::random(40)),
                    'provider' => $provider,
                    'provider_id' => $providerId,
                ]);
            } catch (QueryException $e) {
                // Carrera: otro request creó el usuario en paralelo.
                return User::where('provider', $provider)->where('provider_id', $providerId)->firstOrFail();
            }

            $usuario->forceFill(['email_verified_at' => now()])->save();
            $usuario->assignRole(config('sso.default_role'));

            return $usuario;
        });
    }

    private function refrescar(User $usuario, string $nombre): void
    {
        $cambios = [];

        if ($nombre !== '' && $usuario->name !== $nombre) {
            $cambios['name'] = $nombre;
        }

        if ($usuario->email_verified_at === null) {
            $cambios['email_verified_at'] = now();
        }

        if ($cambios !== []) {
            $usuario->forceFill($cambios)->save();
        }
    }
}
