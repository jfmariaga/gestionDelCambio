<?php

namespace App\Models;

use Database\Factories\RiesgoPredeterminadoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiesgoPredeterminado extends Model
{
    /** @use HasFactory<RiesgoPredeterminadoFactory> */
    use HasFactory;

    protected $table = 'riesgos_predeterminados';

    protected $fillable = ['proceso_id', 'texto', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class);
    }

    public function preguntasClave(): HasMany
    {
        return $this->hasMany(PreguntaClave::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
