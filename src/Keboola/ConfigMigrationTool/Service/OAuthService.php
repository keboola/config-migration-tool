<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;

class OAuthService
{
    /** @var Client */
    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://syrup.keboola.com/oauth-v2/',
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

    public function createCredentials(string $componentId, array $account) : \stdClass
    {
        $response = $this->client->post(sprintf('credentials/%s', $componentId), [
            'body' => \GuzzleHttp\json_encode([
                "id" => $account['id'],
                "authorizedFor" => !isset($account['email']) ? $account['owner'] : $account['email'],
                "data" => [
                    "access_token" => $account['accessToken'],
                    "refresh_token" => $account['refreshToken'],
                ],
            ]),
        ]);

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }

    public function obtainCredentials(string $componentId, array $account) : \stdClass
    {
        // try to get credentials first
        $credentials = null;
        try {
            $credentials = $this->getCredentials($componentId, $account['id']);
        } catch (\Throwable $e) {
        }

        if ($credentials !== null) {
            return $credentials;
        }

        return $this->createCredentials($componentId, $account);
    }
}
