<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;

class GoodDataProvisioningService
{
    /** @var Client */
    private $client;

    public function __construct(string $baseUri, string $manageToken)
    {
        $this->client = new Client([
            'base_uri' => $baseUri . '/',
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN'),
                'X-KBC-ManageApiToken' => $manageToken,
            ],
            'handler' => HandlerStack::create(),
        ]);
    }

    public function addProject(array $params) : void
    {
        $this->client->request('PATCH', 'projects', ['json' => $params]);
    }

    public function addUser(array $params) : void
    {
        $this->client->request('PATCH', 'users', ['json' => $params]);
    }
}
