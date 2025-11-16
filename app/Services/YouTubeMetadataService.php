<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class YouTubeMetadataService
{
    protected $client;
    protected $timeout = 10; // 10-second timeout per spec

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
     * Scrape video title and channel name from YouTube page
     *
     * @param string $videoId YouTube video ID
     * @return array {
     *     'videoTitle' => string|null,
     *     'channelName' => string|null,
     *     'scrapingStatus' => 'success'|'partial'|'failed'
     * }
     */
    public function scrapeMetadata(string $videoId): array
    {
        $watchUrl = "https://www.youtube.com/watch?v={$videoId}";

        try {
            $response = $this->client->get($watchUrl);
            $html = (string) $response->getBody();

            $videoTitle = $this->extractVideoTitle($html);
            $channelName = $this->extractChannelName($html);

            // Determine scraping status
            if ($videoTitle && $channelName) {
                $scrapingStatus = 'success';
            } elseif ($videoTitle || $channelName) {
                $scrapingStatus = 'partial';
            } else {
                $scrapingStatus = 'failed';
            }

            return [
                'videoTitle' => $videoTitle,
                'channelName' => $channelName,
                'scrapingStatus' => $scrapingStatus,
            ];
        } catch (GuzzleException $e) {
            Log::warning('YouTube metadata scraping network error', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);

            return [
                'videoTitle' => null,
                'channelName' => null,
                'scrapingStatus' => 'failed',
            ];
        } catch (\Throwable $e) {
            Log::warning('YouTube metadata scraping error', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);

            return [
                'videoTitle' => null,
                'channelName' => null,
                'scrapingStatus' => 'failed',
            ];
        }
    }

    /**
     * Extract video title from HTML using meta tags
     *
     * @param string $html HTML content from YouTube page
     * @return string|null
     */
    protected function extractVideoTitle(string $html): ?string
    {
        try {
            $crawler = new Crawler($html);

            // Try og:title meta tag first (most reliable)
            $title = $crawler->filter('meta[property="og:title"]')->attr('content');
            if ($title) {
                return trim($title);
            }

            // Fallback: try title tag
            $titleTag = $crawler->filter('title')->text();
            if ($titleTag) {
                // YouTube title format: "Title - YouTube"
                $title = str_replace(' - YouTube', '', $titleTag);
                return trim($title);
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug('Failed to extract video title', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract channel name from HTML using meta tags and schema
     *
     * @param string $html HTML content from YouTube page
     * @return string|null
     */
    protected function extractChannelName(string $html): ?string
    {
        try {
            $crawler = new Crawler($html);

            // Priority 1: Try itemprop="name" link tag (structured data for channel name)
            // This is the most reliable source: <link itemprop="name" content="頻道名稱">
            $name = $crawler->filter('link[itemprop="name"]')->attr('content');
            if ($name) {
                return trim($name);
            }

            // Priority 2: Try og:video:tag meta tag (often contains creator name)
            $tags = $crawler->filter('meta[property="og:video:tag"]')->each(function ($node) {
                return $node->attr('content');
            });

            if (!empty($tags)) {
                // Return first tag (typically the creator)
                return trim($tags[0]);
            }

            // Priority 3: Fallback to schema.org data
            // Look for videoDetails.author in ytInitialData
            if (preg_match('/"author":"([^"]+)"/', $html, $matches)) {
                return trim($matches[1]);
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug('Failed to extract channel name', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
