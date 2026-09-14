<?php

namespace App\Models;

use Database\Factories\AreaNotificacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AreaNotificacion extends Model
{
    /** @use HasFactory<AreaNotificacionFactory> */
    use HasFactory;

    protected $table = 'areas_notificacion';

    protected $fillable = ['clave', 'nombre'];

    public function destinatarios(): HasMany
    {
        return $this->hasMany(DestinatarioArea::class, 'area_id');
    }

    /** Destinatarios activos de un área por su clave. */
    public static function destinatariosDe(string $clave)
    {
        return static::where('clave', $clave)->first()?->destinatarios()->where('activo', true)->get()
            ?? collect();
    }
}
