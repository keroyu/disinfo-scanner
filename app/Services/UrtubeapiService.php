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
     */
    public function validateJsonStructure(array $data): bool
    {
        $required = ['videoId', 'channelId', 'videoTitle', 'channelTitle', 'comments'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new UrtubeapiException('資料格式異常，無法匯入');
            }
        }

        // Validate comments array
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

        return true;
    }
}
