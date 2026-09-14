<?php

namespace App\Enums;

/**
 * Estado de una fila derivada de las secciones 3 (consideraciones) y 4 (riesgos asociados).
 *
 * - Vigente: la respuesta de origen sigue en "Sí".
 * - Huerfana: la respuesta pasó a "No"/"N/A" pero el usuario ya había editado o calificado la
 *   fila, así que se conserva y se pide confirmación antes de eliminarla (FR-030).
 */
enum EstadoFila: string
{
    case Vigente = 'vigente';
    case Huerfana = 'huerfana';
}
