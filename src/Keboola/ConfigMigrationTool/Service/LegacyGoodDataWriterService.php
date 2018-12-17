<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use Keboola\Writer\GoodData\Client;

class LegacyGoodDataWriterService
{
    /** @var Client */
    private $client;

    public function __construct(string $writerUrl)
    {
        $this->client = Client::factory([
          'url' => $writerUrl,
          'token' => getenv('KBC_TOKEN'),
        ]);
    }

    public function listProjects(string $configurationId) : array
    {
        return $this->client->getProjects($configurationId);
    }

    public function listUsers(string $configurationId) : array
    {
        return $this->client->getUsers($configurationId);
    }
}
