<?php

namespace App\Models;

use Database\Factories\PlantaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Planta extends Model
{
    /** @use HasFactory<PlantaFactory> */
    use HasFactory;

    protected $table = 'plantas';

    protected $fillable = ['nombre', 'codigo', 'orden', 'activo'];

    protected $casts = [
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudCambio::class);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }
}
