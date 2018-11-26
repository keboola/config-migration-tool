<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;

class OAuthService
{
    /** @var Client */
    private $client;

    public function __construct($region = 'us-east-1')
    {
        $this->client = new Client([
            'base_uri' => $this->getBaseUrl($region),
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN'),
            ],
            'handler' => HandlerStack::create(),
        ]);
    }

    private function getBaseUrl($region)
    {
        if ($region === 'us-east-1') {
            return 'https://syrup.keboola.com/oauth-v2/';
        } else if ($region === 'eu-central-1' || $region === 'ap-southeast-2') {
            return sprintf('https://syrup.%s.keboola.com/oauth-v2/', $region);
        }

        throw new \Exception(sprintf('Unknown region "%s"', $region));
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
                "authorizedFor" => empty($account['email']) ? $account['owner'] : $account['email'],
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
