<?php

namespace App\Exceptions;

use Exception;

class YouTubePageException extends Exception
{
    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error' => 'youtube_page_error'
        ], 502);
    }
}
