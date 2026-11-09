<x-guest-layout>
    <h2 class="text-xl font-bold text-ink-900 dark:text-gray-100">Iniciar sesión</h2>
    <p class="mt-1 text-sm text-ink-secondary dark:text-gray-400">Ingresa con tu cuenta para continuar.</p>

    <!-- Session Status -->
    <x-auth-session-status class="mt-4" :status="session('status')" />

    @if (config('sso.enabled'))
        <a href="{{ route('sso.redirect') }}"
           class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-line bg-surface px-4 py-2.5 text-sm font-medium text-ink-900 shadow-sm transition hover:bg-surface-alt dark:border-white/10 dark:bg-surface-dark dark:text-gray-200 dark:hover:bg-white/5">
            <svg class="h-4 w-4" viewBox="0 0 21 21" aria-hidden="true">
                <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
                <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
            </svg>
            {{ config('sso.button_label') }}
        </a>

        <div class="my-4 flex items-center gap-3 text-xs text-ink-secondary dark:text-gray-400">
            <span class="h-px flex-1 bg-line dark:bg-white/10"></span>
            o con tu correo
            <span class="h-px flex-1 bg-line dark:bg-white/10"></span>
        </div>
    @else
        <div class="mt-6"></div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Correo electrónico')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Contraseña')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me / Forgot Password -->
        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-line text-brand-600 shadow-sm focus:ring-brand-500 dark:border-white/10 dark:bg-surface-dark" name="remember">
                <span class="ms-2 text-sm text-ink-secondary dark:text-gray-400">{{ __('Recordarme') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-brand-600 hover:text-brand-700 hover:underline dark:text-brand-300 dark:hover:text-brand-200 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500" href="{{ route('password.request') }}">
                    {{ __('¿Olvidaste tu contraseña?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="w-full mt-5">
            {{ __('Iniciar sesión') }}
        </x-primary-button>
    </form>

    @if (Route::has('register'))
        <p class="mt-6 text-center text-sm text-ink-secondary dark:text-gray-400">
            {{ __('¿No tienes cuenta?') }}
            <a class="font-medium text-brand-600 hover:text-brand-700 hover:underline dark:text-brand-300 dark:hover:text-brand-200" href="{{ route('register') }}">{{ __('Regístrate') }}</a>
        </p>
    @endif
</x-guest-layout>
