<?php

namespace App\Models;

use Database\Factories\DestinatarioAreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestinatarioArea extends Model
{
    /** @use HasFactory<DestinatarioAreaFactory> */
    use HasFactory;

    protected $table = 'destinatarios_area';

    protected $fillable = ['area_id', 'user_id', 'email', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaNotificacion::class, 'area_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Correo efectivo del destinatario (usuario interno o correo externo). */
    public function correo(): ?string
    {
        return $this->usuario?->email ?? $this->email;
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
