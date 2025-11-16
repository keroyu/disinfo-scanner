<?php

namespace App\Services;

use App\Exceptions\UrtubeapiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class UrtubeapiService
{
    protected $client;
    protected $baseUrl = 'https://urtubeapi.analysis.tw/api/api_comment.php';
    protected $timeout = 30;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * Fetch comment data from urtubeapi endpoint with pagination support
     *
     * @param string $videoId YouTube video ID
     * @param string $channelId Channel ID (used as token for urtubeapi)
     * @param string|null $pageToken Optional pagination token for fetching additional pages
     * @return array Comment data with optional nextPageToken for pagination
     */
    public function fetchCommentData($videoId, $channelId, $pageToken = null): array
    {
        try {
            $query = [
                'videoId' => $videoId,
                'token' => $channelId,
            ];

            // Add pagination support if pageToken is provided
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $response = $this->client->get($this->baseUrl, [
                'query' => $query
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if ($data === null) {
                throw new UrtubeapiException('資料格式異常，無法匯入');
            }

            $this->validateJsonStructure($data);
            return $data;
        } catch (GuzzleException $e) {
            throw new UrtubeapiException('無法連接到資料來源，請稍後再試');
        } catch (\Throwable $e) {
            if ($e instanceof UrtubeapiException) {
                throw $e;
            }
            throw new UrtubeapiException('無法連接到資料來源，請稍後再試');
        }
    }

    /**
     * Validate JSON structure from urtubeapi
     * Supports both old format and new format from API
     * IMPORTANT: channelId is NOT in API response (it's a request parameter only)
     */
    public function validateJsonStructure(array &$data): bool
    {
        // Check for new API format with 'result' and 'authors'
        if (isset($data['result']) && is_array($data['result'])) {
            // Transform new format to expected format
            $this->transformNewFormat($data);
            return true;
        }

        // Check for required videoId (channelId comes from request parameter, not response)
        // Support both camelCase and snake_case
        if (!isset($data['videoId']) && !isset($data['video_id'])) {
            throw new UrtubeapiException('資料格式異常，無法匯入');
        }

        // If comments array exists, validate it
        if (isset($data['comments'])) {
            if (!is_array($data['comments'])) {
                throw new UrtubeapiException('資料格式異常，無法匯入');
            }

            // Validate first comment structure if exists
            // API returns comments as object where keys are comment IDs
            if (!empty($data['comments'])) {
                // Get first comment (API returns object, not array)
                $firstCommentId = array_key_first($data['comments']);
                if ($firstCommentId !== null) {
                    $comment = $data['comments'][$firstCommentId];

                    // Comment must be an array and have required fields
                    if (!is_array($comment)) {
                        throw new UrtubeapiException('資料格式異常，無法匯入');
                    }

                    // Required fields in comment data
                    $hasAuthorChannelId = isset($comment['authorChannelId']) || isset($comment['author_channel_id']);
                    $hasText = isset($comment['textDisplay']) || isset($comment['textOriginal']) || isset($comment['text']);

                    if (!($hasAuthorChannelId && $hasText)) {
                        throw new UrtubeapiException('資料格式異常，無法匯入');
                    }
                }
            }
        }

        return true;
    }

    /**
     * Transform new API format to expected format
     */
    protected function transformNewFormat(array &$data): void
    {
        // Extract comments from 'result' field
        if (isset($data['result']) && is_array($data['result'])) {
            $data['comments'] = $data['result'];
        }

        // Use 'authors' as channelTitle if available
        if (isset($data['authors']) && !isset($data['channelTitle'])) {
            $data['channelTitle'] = null;
        }

        // Add default values for missing fields
        if (!isset($data['videoTitle'])) {
            $data['videoTitle'] = null;
        }
        if (!isset($data['channelTitle'])) {
            $data['channelTitle'] = null;
        }
    }
}
