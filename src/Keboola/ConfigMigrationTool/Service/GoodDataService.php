<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use Keboola\GoodData\Client;

class GoodDataService
{
    /** @var Client */
    private $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function login(string $baseUri, string $login, string $password) : void
    {
        $this->client->setApiUrl($baseUri);
        $this->client->login($login, $password);
    }

    public function getProject(string $pid) : array
    {
        return $this->client->getProjects()->getProject($pid)['project'];
    }
}
