<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password', 'provider', 'provider_id'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** Procesos que este usuario tiene asignados como dueño de proceso (FR-040). */
    public function procesos(): BelongsToMany
    {
        return $this->belongsToMany(Proceso::class, 'proceso_usuario');
    }

    /** Planta / sede que el usuario ve seleccionada al ingresar (Revisión R2 / US12). */
    public function plantaPreferida(): BelongsTo
    {
        return $this->belongsTo(Planta::class, 'planta_preferida_id');
    }

    /** Solicitudes de las que este usuario es responsable / líder del cambio. */
    public function solicitudesCreadas()
    {
        return $this->hasMany(SolicitudCambio::class, 'created_by');
    }

    /** ¿Es dueño del proceso con este nombre? (secciones 3 y 4, FR-040). */
    public function esDuenoDeProceso(string $nombreProceso): bool
    {
        return $this->hasRole('dueno_proceso')
            && $this->procesos()->where('nombre', $nombreProceso)->exists();
    }

    /** ¿La cuenta se creó / vinculó por Single Sign-On (Entra ID)? */
    public function isSsoUser(): bool
    {
        return filled($this->provider);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
