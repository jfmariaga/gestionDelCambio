<?php

namespace App\Livewire\Notificaciones;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Campana extends Component
{
    private const MAX_RECIENTES = 8;

    #[Computed]
    public function noLeidas(): int
    {
        return Auth::user()?->unreadNotifications()->count() ?? 0;
    }

    #[Computed]
    public function recientes()
    {
        return Auth::user()?->notifications()->latest()->limit(self::MAX_RECIENTES)->get() ?? collect();
    }

    public function marcarLeidaYAbrir(string $id, ?string $url): mixed
    {
        Auth::user()?->notifications()->whereKey($id)->update(['read_at' => now()]);
        unset($this->noLeidas, $this->recientes);

        return $url ? $this->redirect($url) : null;
    }

    public function marcarTodas(): void
    {
        Auth::user()?->unreadNotifications->markAsRead();
        unset($this->noLeidas, $this->recientes);
    }

    public function render()
    {
        return view('livewire.notificaciones.campana');
    }
}
