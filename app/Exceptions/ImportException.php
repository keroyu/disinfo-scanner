<?php

namespace App\Exceptions;

use Exception;

class ImportException extends Exception
{
    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error' => 'import_error'
        ], 500);
    }
}
