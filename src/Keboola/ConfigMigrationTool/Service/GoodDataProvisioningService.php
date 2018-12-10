<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;

class GoodDataProvisioningService
{
    /** @var Client */
    private $client;

    public function __construct(string $baseUri)
    {
        $this->client = new Client([
            'base_uri' => $baseUri . '/',
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN'),
            ],
            'handler' => HandlerStack::create(),
        ]);
    }

    public function addProject(array $params) : void
    {
        $provisioningParams = [
            'json' => $params,
        ];
        $this->client->request('POST', 'projects', $provisioningParams);
    }
}
