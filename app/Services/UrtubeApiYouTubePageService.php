<?php

namespace App\Services;

use App\Exceptions\YouTubePageException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class UrtubeApiYouTubePageService
{
    protected $client;
    protected $timeout = 30;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => $this->timeout,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
    }

    /**
     * Fetch YouTube page source HTML
     */
    public function fetchPageSource($videoUrl): string
    {
        try {
            $response = $this->client->get($videoUrl);
            return (string) $response->getBody();
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                throw new YouTubePageException('找不到該 YouTube 影片');
            }
            throw new YouTubePageException('無法訪問 YouTube，請檢查網路連線或稍後再試');
        } catch (\Throwable $e) {
            throw new YouTubePageException('無法訪問 YouTube，請檢查網路連線或稍後再試');
        }
    }

    /**
     * Extract channelId from YouTube page HTML
     */
    public function extractChannelIdFromSource($html): string
    {
        // Look for "channelId":"UC..."
        if (preg_match('/"channelId":"(UC[a-zA-Z0-9_-]{22})"/', $html, $matches)) {
            return $matches[1];
        }

        // Alternative: search for channelId in different formats
        if (preg_match('/channelId["\']?\s*:\s*["\']?(UC[a-zA-Z0-9_-]{22})["\']?/i', $html, $matches)) {
            return $matches[1];
        }

        throw new YouTubePageException('無法從 YouTube 頁面取得頻道資訊，請改用 urtubeapi 網址');
    }

    /**
     * Get full YouTube watch URL
     */
    public function getWatchUrl($videoId): string
    {
        return "https://www.youtube.com/watch?v={$videoId}";
    }
}
