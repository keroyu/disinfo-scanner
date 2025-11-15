<?php

namespace App\Exceptions;

use Exception;

class ValidationException extends Exception
{
    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error' => 'validation_error'
        ], 422);
    }
}
