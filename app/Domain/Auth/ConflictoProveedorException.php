<?php

namespace App\Domain\Auth;

use RuntimeException;

/**
 * El correo del usuario que ingresa por SSO ya está vinculado a otra identidad
 * (otro `provider` / `provider_id`).
 */
class ConflictoProveedorException extends RuntimeException {}
