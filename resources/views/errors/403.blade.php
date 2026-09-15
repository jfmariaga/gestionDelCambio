@include('errors.minimal', [
    'code' => 403,
    'title' => 'Acceso no autorizado',
    'message' => $exception->getMessage() ?: 'No tienes permiso para realizar esta acción.',
])
