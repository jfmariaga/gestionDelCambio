# SSO con Microsoft Entra ID — puesta en marcha

La aplicación permite ingresar con la cuenta corporativa de Microsoft 365 / Entra ID
**además** del login local con correo y contraseña (ambos siguen disponibles).

- Paquetes: `laravel/socialite` + `socialiteproviders/microsoft-azure` (driver `azure`).
- Interruptor: variable `SSO_ENABLED`. En `false` (o sin credenciales) el botón no aparece y
  `/auth/microsoft/redirect` y `/auth/microsoft/callback` responden **404**.
- Usuario nuevo por SSO: se crea al vuelo con rol **`consulta`** y correo verificado. Un
  administrador le ajusta el rol, la planta y (si aplica) los procesos en
  **Administración → Usuarios y roles**.
- Si el correo del IdP coincide con un usuario local existente, la cuenta se **vincula**
  (conserva su rol). Ver la nota de seguridad al final.

## 1. Registrar la aplicación en Entra ID

1. **Microsoft Entra admin center → App registrations → New registration.**
2. Nombre: p. ej. *Gestión del Cambio — SSO*.
3. *Supported account types*: **Accounts in this organizational directory only (Single tenant)**.
4. *Redirect URI* → plataforma **Web**, agregar:
   - `https://<dominio-produccion>/auth/microsoft/callback`
   - `http://localhost:8000/auth/microsoft/callback` (desarrollo)
5. **Register.** Copiar de la pantalla *Overview*:
   - *Application (client) ID* → `AZURE_CLIENT_ID`
   - *Directory (tenant) ID* → `AZURE_TENANT_ID`

## 2. Secreto de cliente

**Certificates & secrets → New client secret** → copiar el **Value** (no el *Secret ID*) →
`AZURE_CLIENT_SECRET`. Anotar la fecha de expiración y programar su rotación.

## 3. Permisos

**API permissions → Add a permission → Microsoft Graph → Delegated permissions**:
`openid`, `profile`, `email`, `User.Read`. Luego **Grant admin consent for <tenant>**.

Si los correos no llegan en el token, en **Token configuration** agregar el *optional claim*
`email` (y/o `upn`) al *ID token*.

## 4. (Opcional) Cierre de sesión federado — Fase 2

En **Authentication** agregar *Front-channel logout URL* / *post logout redirect URI*
`https://<dominio-produccion>/`. Luego poner `SSO_LOGOUT_FROM_IDP=true`.

## 5. Variables de entorno

```env
SSO_ENABLED=true
SSO_DEFAULT_ROLE=consulta
SSO_ALLOWED_DOMAINS=            # vacío = cualquier cuenta del tenant; o "levapan.com,panal.com.co"
SSO_LOGOUT_FROM_IDP=false
AZURE_CLIENT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
AZURE_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
AZURE_TENANT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
AZURE_REDIRECT_URI="${APP_URL}/auth/microsoft/callback"
```

Tras editar `.env` en el servidor: `php artisan config:cache`.

## 6. Verificación

```sh
php artisan migrate
php artisan route:list --name=sso     # deben salir sso.redirect y sso.callback
php artisan test --filter=Sso
```

Manual (con credenciales reales y `SSO_ENABLED=true`):

1. `/login` muestra **"Continuar con Microsoft"**.
2. Click → pantalla de Microsoft → consentimiento → vuelve a la app → selector de planta → tablero.
3. En `users`: fila con `provider=azure`, `provider_id`, `email_verified_at` y rol `consulta`.
4. Cerrar sesión y volver a entrar con **correo/contraseña local** → sigue funcionando.
5. Volver a entrar por SSO → no se duplica el usuario.
6. `SSO_ENABLED=false` + `php artisan config:cache` → el botón desaparece y
   `/auth/microsoft/redirect` da 404.

## Nota de seguridad — vinculación por correo

Cuando un usuario entra por SSO y su correo coincide con una cuenta local **sin** identidad
externa, la cuenta se vincula automáticamente. Esto es aceptable porque el registro de la app
en Azure es **single-tenant**: solo los buzones verificados de la organización pueden completar
el flujo, y las cuentas locales las crea un administrador.

Si en el futuro se admite **multi-tenant** o identidades externas/no verificadas, hay que
endurecer este paso: exigir el claim `email_verified = true`, o quitar la vinculación
automática y obligar a un administrador a vincular identidades a mano.
