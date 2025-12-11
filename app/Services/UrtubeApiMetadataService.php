<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class UrtubeApiMetadataService
{
    protected $client;
    protected $timeout = 10; // 10-second timeout per spec

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => $this->timeout,
            'headers' => [
                // More complete User-Agent to avoid bot detection
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ]
        ]);
    }

    /**
     * Scrape video title, channel name, and published date from YouTube
     * Uses oEmbed API first (reliable), falls back to page scraping
     *
     * @param string $videoId YouTube video ID
     * @return array {
     *     'videoTitle' => string|null,
     *     'channelName' => string|null,
     *     'publishedAt' => string|null (ISO 8601 date),
     *     'scrapingStatus' => 'success'|'partial'|'failed'
     * }
     */
    public function scrapeMetadata(string $videoId): array
    {
        // Try oEmbed API first (most reliable, works on cloud servers)
        $oembedResult = $this->fetchFromOembed($videoId);

        if ($oembedResult['videoTitle'] && $oembedResult['channelName']) {
            Log::debug('Metadata fetched via oEmbed API', ['video_id' => $videoId]);
            return $oembedResult;
        }

        // Fallback to page scraping if oEmbed fails
        Log::debug('oEmbed failed, trying page scraping', ['video_id' => $videoId]);
        return $this->scrapeFromPage($videoId);
    }

    /**
     * Fetch metadata from YouTube oEmbed API
     * This is more reliable than page scraping, especially on cloud servers
     */
    protected function fetchFromOembed(string $videoId): array
    {
        $oembedUrl = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$videoId}&format=json";

        try {
            $response = $this->client->get($oembedUrl);
            $data = json_decode((string) $response->getBody(), true);

            if ($data && isset($data['title']) && isset($data['author_name'])) {
                return [
                    'videoTitle' => $data['title'],
                    'channelName' => $data['author_name'],
                    'publishedAt' => null, // oEmbed doesn't provide publish date
                    'scrapingStatus' => 'success',
                ];
            }

            return [
                'videoTitle' => $data['title'] ?? null,
                'channelName' => $data['author_name'] ?? null,
                'publishedAt' => null,
                'scrapingStatus' => ($data['title'] ?? null) || ($data['author_name'] ?? null) ? 'partial' : 'failed',
            ];
        } catch (GuzzleException $e) {
            Log::debug('oEmbed API request failed', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);

            return [
                'videoTitle' => null,
                'channelName' => null,
                'publishedAt' => null,
                'scrapingStatus' => 'failed',
            ];
        }
    }

    /**
     * Scrape metadata from YouTube page (fallback method)
     */
    protected function scrapeFromPage(string $videoId): array
    {
        $watchUrl = "https://www.youtube.com/watch?v={$videoId}";

        try {
            $response = $this->client->get($watchUrl);
            $html = (string) $response->getBody();

            $videoTitle = $this->extractVideoTitle($html);
            $channelName = $this->extractChannelName($html);
            $publishedAt = $this->extractPublishedDate($html);

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
                'publishedAt' => $publishedAt,
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
                'publishedAt' => null,
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
                'publishedAt' => null,
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

    /**
     * Extract published date from HTML using meta tags
     *
     * @param string $html HTML content from YouTube page
     * @return string|null ISO 8601 date string
     */
    protected function extractPublishedDate(string $html): ?string
    {
        try {
            $crawler = new Crawler($html);

            // Try itemprop="datePublished" meta tag (common in YouTube structured data)
            $publishedDate = $crawler->filter('meta[itemprop="datePublished"]')->attr('content');
            if ($publishedDate) {
                return $publishedDate; // Usually already in ISO 8601 format
            }

            // Fallback: search for uploadDate in JSON-LD schema
            if (preg_match('/"uploadDate":"([^"]+)"/', $html, $matches)) {
                return $matches[1];
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug('Failed to extract published date', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
