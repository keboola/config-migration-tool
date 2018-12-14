<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;
use Psr\Http\Message\ResponseInterface;

class OAuthV3Service
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

    public function getCredentials(string $componentId, string $id) : array
    {
        $response = $this->client->get(sprintf('credentials/%s/%s', $componentId, $id));

        return \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
    }

    public function createCredentials(string $componentId, array $credentials) : array
    {
        $response = $this->client->post(sprintf('credentials/%s', $componentId), [
            'body' => \GuzzleHttp\json_encode($credentials),
        ]);

        return \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
    }

    public function deleteCredentials(string $componentId, string $name) : ResponseInterface
    {
        return $this->client->delete(sprintf('credentials/%s/%s', $componentId, $name));
    }
}
