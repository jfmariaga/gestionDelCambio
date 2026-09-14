<?php

namespace App\Models;

use Database\Factories\AdjuntoEvidenciaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class AdjuntoEvidencia extends Model
{
    /** @use HasFactory<AdjuntoEvidenciaFactory> */
    use HasFactory;

    protected $table = 'adjuntos_evidencia';

    protected $fillable = [
        'adjuntable_type', 'adjuntable_id',
        'disco', 'ruta', 'nombre_original', 'mime', 'tamano', 'subido_por',
    ];

    protected $casts = ['tamano' => 'integer'];

    protected static function booted(): void
    {
        static::deleting(function (AdjuntoEvidencia $adjunto) {
            Storage::disk($adjunto->disco)->delete($adjunto->ruta);
        });
    }

    public function adjuntable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function tamanoLegible(): string
    {
        $bytes = $this->tamano;

        foreach (['B', 'KB', 'MB', 'GB'] as $unidad) {
            if ($bytes < 1024) {
                return round($bytes, 1).' '.$unidad;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }
}
