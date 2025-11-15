<?php

namespace App\Services;

use App\Exceptions\InvalidUrlException;
use App\Exceptions\UrlParsingException;

class UrlParsingService
{
    /**
     * Identify URL type and extract parameters
     */
    public function identify($url): string
    {
        if (empty($url)) {
            throw new InvalidUrlException('請輸入網址');
        }

        if (str_contains($url, 'urtubeapi.analysis.tw')) {
            $this->validateUrtubeapiUrl($url);
            return 'urtubeapi';
        } elseif ($this->isYouTubeUrl($url)) {
            return 'youtube';
        }

        throw new InvalidUrlException('請輸入有效的 urtubeapi 或 YouTube 影片網址');
    }

    /**
     * Check if URL is a valid YouTube URL format
     */
    private function isYouTubeUrl($url): bool
    {
        return preg_match('/^https?:\/\/(www\.|m\.)?youtube\.com\/watch\?v=[\w-]+|^https?:\/\/youtu\.be\/[\w-]+/i', $url) === 1;
    }

    /**
     * Extract videoId from YouTube URL
     */
    public function extractVideoIdFromUrl($url): string
    {
        // Standard: https://www.youtube.com/watch?v=VIDEO_ID
        if (preg_match('/youtube\.com\/watch\?v=([\w-]+)/i', $url, $matches)) {
            return $matches[1];
        }

        // Short: https://youtu.be/VIDEO_ID
        if (preg_match('/youtu\.be\/([\w-]+)/i', $url, $matches)) {
            return $matches[1];
        }

        // Mobile: https://m.youtube.com/watch?v=VIDEO_ID
        if (preg_match('/m\.youtube\.com\/watch\?v=([\w-]+)/i', $url, $matches)) {
            return $matches[1];
        }

        throw new UrlParsingException('無法識別的 YouTube 網址格式，請檢查網址是否正確');
    }

    /**
     * Validate urtubeapi URL format
     */
    public function validateUrtubeapiUrl($url): bool
    {
        // Must contain videoId parameter
        if (!preg_match('/videoId=([^&]+)/i', $url, $videoMatch)) {
            throw new InvalidUrlException('urtubeapi 網址缺少必要參數（videoId 或 token）');
        }

        // Must contain token parameter (which is channelId)
        if (!preg_match('/token=([^&]+)/i', $url, $tokenMatch)) {
            throw new InvalidUrlException('urtubeapi 網址缺少必要參數（videoId 或 token）');
        }

        return true;
    }

    /**
     * Extract videoId and channelId from urtubeapi URL
     */
    public function extractFromUrtubeapiUrl($url): array
    {
        if (!preg_match('/videoId=([^&]+)/i', $url, $videoMatch)) {
            throw new UrlParsingException('無法從 urtubeapi 網址提取 videoId');
        }

        if (!preg_match('/token=([^&]+)/i', $url, $tokenMatch)) {
            throw new UrlParsingException('無法從 urtubeapi 網址提取 token');
        }

        return [
            'videoId' => $videoMatch[1],
            'channelId' => $tokenMatch[1],
        ];
    }
}
