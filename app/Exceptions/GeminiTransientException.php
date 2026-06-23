<?php

namespace App\Exceptions;

use RuntimeException;

class GeminiTransientException extends RuntimeException
{
    // Usada para errores 500, 502, 503, 504 de la API de Gemini
}
