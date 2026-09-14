<?php

namespace App\Models;

use Database\Factories\ProcesoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proceso extends Model
{
    /** @use HasFactory<ProcesoFactory> */
    use HasFactory;

    protected $table = 'procesos';

    protected $fillable = ['nombre', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function preguntasClave(): HasMany
    {
        return $this->hasMany(PreguntaClave::class);
    }

    public function riesgosPredeterminados(): HasMany
    {
        return $this->hasMany(RiesgoPredeterminado::class);
    }

    public function duenos(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proceso_usuario');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }
}
