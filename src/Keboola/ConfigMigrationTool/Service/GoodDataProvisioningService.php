<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;

class GoodDataProvisioningService
{
    /** @var Client */
    private $client;

    /** @var int  */
    private $productionProjectsCount;

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
        $this->productionProjectsCount = 0;
    }

    public function addProject(string $pid, array $params) : void
    {
        if (isset($params['keboolaToken']) && $params['keboolaToken'] == 'production') {
            $this->productionProjectsCount++;
        }
        $this->client->request('PUT', "projects/$pid", ['json' => $params]);
    }

    public function addUser(string $email, array $params) : void
    {
        $this->client->request('PUT', "users/$email", ['json' => $params]);
    }

    public function getProductionProjectsCount(): int
    {
        return $this->productionProjectsCount;
    }
}
