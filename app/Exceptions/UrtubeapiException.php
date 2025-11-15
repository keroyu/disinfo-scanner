<?php

namespace App\Exceptions;

use Exception;

class UrtubeapiException extends Exception
{
    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error' => 'urtubeapi_error'
        ], 502);
    }
}
