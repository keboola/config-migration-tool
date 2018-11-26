<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;

class OAuthV3Service
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
            return 'https://oauth.keboola.com/';
        } else if ($region === 'eu-central-1' || $region === 'ap-southeast-2') {
            return sprintf('https://oauth.%s.keboola.com/', $region);
        }

        throw new \Exception(sprintf('Unknown region "%s"', $region));
    }

    public function getCredentials(string $componentId, string $id) : \stdClass
    {
        $response = $this->client->get(sprintf('credentials/%s/%s', $componentId, $id));

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }

    public function createCredentials(string $componentId, array $credentials) : array
    {
        $response = $this->client->post(sprintf('credentials/%s', $componentId), [
            'body' => \GuzzleHttp\json_encode([
                'id' => $credentials['id'],
                'authorizedFor' => empty($credentials['email']) ? $credentials['owner'] : $credentials['email'],
                'data' => $credentials['data'],
            ]),
        ]);

        return \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
    }
}
