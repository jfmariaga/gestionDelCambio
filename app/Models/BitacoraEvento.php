<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BitacoraEvento extends Model
{
    protected $table = 'bitacora_eventos';

    public $timestamps = false;

    protected $fillable = ['solicitud_cambio_id', 'user_id', 'evento', 'comentario', 'datos', 'created_at'];

    protected $casts = [
        'datos' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BitacoraEvento $evento) {
            $evento->created_at ??= now();
        });
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCambio::class, 'solicitud_cambio_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
