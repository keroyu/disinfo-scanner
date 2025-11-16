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
     * Fetch comment data from urtubeapi endpoint
     */
    public function fetchCommentData($videoId, $channelId): array
    {
        try {
            $response = $this->client->get($this->baseUrl, [
                'query' => [
                    'videoId' => $videoId,
                    'token' => $channelId,
                ]
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
     */
    public function validateJsonStructure(array &$data): bool
    {
        // Check for new API format with 'result' and 'authors'
        if (isset($data['result']) && is_array($data['result'])) {
            // Transform new format to expected format
            $this->transformNewFormat($data);
            return true;
        }

        // Check for old format with 'comments' directly
        $required = ['videoId', 'channelId'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new UrtubeapiException('資料格式異常，無法匯入');
            }
        }

        // If comments array exists, validate it
        if (isset($data['comments'])) {
            if (!is_array($data['comments'])) {
                throw new UrtubeapiException('資料格式異常，無法匯入');
            }

            // Validate first comment structure if exists
            if (!empty($data['comments'])) {
                $comment = $data['comments'][0];
                $commentRequired = ['commentId', 'author', 'authorChannelId', 'text'];

                foreach ($commentRequired as $field) {
                    if (!isset($comment[$field])) {
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
