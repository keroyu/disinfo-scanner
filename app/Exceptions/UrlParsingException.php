<?php

namespace App\Exceptions;

use Exception;

class UrlParsingException extends Exception
{
    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error' => 'url_parsing_error'
        ], 400);
    }
}
