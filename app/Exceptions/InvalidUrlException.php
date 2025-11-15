<?php

namespace App\Exceptions;

use Exception;

class InvalidUrlException extends Exception
{
    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error' => 'invalid_url'
        ], 400);
    }
}
