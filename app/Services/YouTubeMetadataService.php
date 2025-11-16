<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class YouTubeMetadataService
{
    protected $client;
    protected $timeout = 10; // 10 second timeout as per spec

    /**
     * Initialize Guzzle client with timeout
     */
    public function __construct()
    {
        $this->client = new Client([
            'timeout' => $this->timeout,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
    }

    /**
     * Scrape video title and channel name from YouTube page
     * Returns structured metadata with status
     *
     * @param string $videoId YouTube video ID
     * @return array ['videoTitle' => string|null, 'channelName' => string|null, 'scrapingStatus' => 'success'|'partial'|'failed']
     */
    public function scrapeMetadata(string $videoId): array
    {
        $watchUrl = "https://www.youtube.com/watch?v={$videoId}";

        try {
            // Fetch YouTube page
            $response = $this->client->get($watchUrl);
            $html = (string) $response->getBody();

            // Extract metadata
            $videoTitle = $this->extractVideoTitle($html);
            $channelName = $this->extractChannelName($html);

            // Determine status based on what was extracted
            if ($videoTitle && $channelName) {
                $status = 'success';
            } elseif ($videoTitle || $channelName) {
                $status = 'partial';
            } else {
                $status = 'failed';
            }

            Log::info('YouTube metadata scraping', [
                'video_id' => $videoId,
                'status' => $status,
                'has_title' => !is_null($videoTitle),
                'has_channel' => !is_null($channelName),
            ]);

            return [
                'videoTitle' => $videoTitle,
                'channelName' => $channelName,
                'scrapingStatus' => $status,
            ];
        } catch (GuzzleException $e) {
            Log::warning('YouTube metadata scraping - HTTP error', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'videoTitle' => null,
                'channelName' => null,
                'scrapingStatus' => 'failed',
            ];
        } catch (\Throwable $e) {
            Log::error('YouTube metadata scraping - Unexpected error', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'videoTitle' => null,
                'channelName' => null,
                'scrapingStatus' => 'failed',
            ];
        }
    }

    /**
     * Extract video title from HTML meta tags
     * Uses multiple strategies for robustness
     *
     * @param string $html HTML content
     * @return string|null Video title or null if not found
     */
    protected function extractVideoTitle(string $html): ?string
    {
        try {
            $crawler = new Crawler($html);

            // Strategy 1: Look for og:title meta tag
            $titleElements = $crawler->filterXPath('//meta[@property="og:title"]/@content');
            if (count($titleElements) > 0) {
                $value = $titleElements->getNode(0)->nodeValue;
                if (!empty($value)) {
                    return $value;
                }
            }

            // Strategy 2: Look for title meta tag
            $titleElements = $crawler->filterXPath('//meta[@name="title"]/@content');
            if (count($titleElements) > 0) {
                $value = $titleElements->getNode(0)->nodeValue;
                if (!empty($value)) {
                    return $value;
                }
            }

            // Strategy 3: Look for <title> tag
            $titleElements = $crawler->filter('title');
            if (count($titleElements) > 0) {
                $value = trim($titleElements->text());
                // Remove " - YouTube" suffix if present
                $value = preg_replace('/\s*-\s*YouTube\s*$/', '', $value);
                if (!empty($value)) {
                    return $value;
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('Failed to extract video title', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract channel name from HTML
     * Uses multiple extraction strategies for robustness
     *
     * @param string $html HTML content
     * @return string|null Channel name or null if not found
     */
    protected function extractChannelName(string $html): ?string
    {
        try {
            $crawler = new Crawler($html);

            // Strategy 1: Look for ytInitialData JSON in script tag
            // This contains structured data with channel name
            if (preg_match('/var ytInitialData = ({.*?});/', $html, $matches)) {
                try {
                    $data = json_decode($matches[1], true);

                    // Navigate through ytInitialData structure to find channel name
                    if (isset($data['metadata']['playlistMetadataRenderer']['title'])) {
                        return $data['metadata']['playlistMetadataRenderer']['title'];
                    }

                    // Alternative path in metadata
                    if (isset($data['metadata']['videoMetadataRenderer']['title'])) {
                        return $data['metadata']['videoMetadataRenderer']['title'];
                    }

                    // Try sidebar info
                    if (isset($data['sidebar'])) {
                        $sidebar = $data['sidebar']['playlistSidebarRenderer']['items'][1] ?? null;
                        if ($sidebar && isset($sidebar['playlistSidebarPrimaryInfoRenderer']['title']['runs'][0]['text'])) {
                            return $sidebar['playlistSidebarPrimaryInfoRenderer']['title']['runs'][0]['text'];
                        }
                    }
                } catch (\Throwable $e) {
                    // JSON parsing failed, try other strategies
                    Log::debug('Failed to parse ytInitialData JSON', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Strategy 2: Look for channel name in og:site_name or similar meta tags
            $channelMetaElements = $crawler->filterXPath('//meta[@property="og:site_name"]/@content');
            if (count($channelMetaElements) > 0) {
                $value = $channelMetaElements->getNode(0)->nodeValue;
                if (!empty($value) && $value !== 'YouTube') {
                    return $value;
                }
            }

            // Strategy 3: Look for channel link in HTML
            // Find pattern: /channel/UCXX or /@username
            if (preg_match('/\/(@[a-zA-Z0-9_-]+|channel\/UC[a-zA-Z0-9_-]{22})/', $html, $matches)) {
                // This is a channel identifier, not a name - use regex to find actual name
                // Look for text near channel links
                if (preg_match('/>([^<]{2,100})<\/a>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/yt-formatted-string>', $html, $nameMatches)) {
                    return trim($nameMatches[1]);
                }
            }

            // Strategy 4: Look for channel name in common YouTube page elements
            if (preg_match('/title="([^"]+)"\s*href="\/channel\/UC[a-zA-Z0-9_-]{22}"/', $html, $matches)) {
                return $matches[1];
            }

            // Strategy 5: Search for attribute patterns (channel name in attributes)
            if (preg_match('/"title"\s*:\s*\{\s*"simpleText"\s*:\s*"([^"]+)"\s*\}\s*,\s*"navigationEndpoint"[^}]*"channelId"/', $html, $matches)) {
                return $matches[1];
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('Failed to extract channel name', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
