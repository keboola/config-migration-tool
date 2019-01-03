<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;

class OAuthV2Service
{
    /** @var Client */
    private $client;

    public function __construct(string $baseUrl)
    {
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN'),
            ],
            'handler' => HandlerStack::create(),
        ]);
    }
    public function getCredentials(string $componentId, string $id) : \stdClass
    {
        $response = $this->client->get(sprintf('credentials/%s/%s', $componentId, $id));

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }

    public function getCredentialsRaw(string $componentId, string $id) : \stdClass
    {
        $response = $this->client->get(sprintf('credentials/%s/%s/raw', $componentId, $id));

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }

    public function createCredentials(string $componentId, array $account) : \stdClass
    {
        $body = [
            "id" => $account['id'],
            "authorizedFor" => empty($account['email']) ? $account['owner'] : $account['email'],
            "data" => [
                "access_token" => $account['accessToken'],
                "refresh_token" => $account['refreshToken'],
            ],
        ];
        if (!empty($account['appKey'])) {
            $body['appKey'] = $account['appKey'];
        }
        if (!empty($account['appSecretDocker'])) {
            $body['appSecretDocker'] = $account['appSecretDocker'];
        }
        if (!empty($account['authUrl'])) {
            $body['authUrl'] = $account['authUrl'];
        }
        $response = $this->client->post(sprintf('credentials/%s', $componentId), [
            'body' => \GuzzleHttp\json_encode($body),
        ]);

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }
}
