<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\KeboolaExGoogleAnalyticsV4Migration;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class KeboolaExGoogleAnalyticsV4MigrationTest extends TestCase
{
    /** @var Client */
    private $storageApiClient;

    /** @var Components */
    private $components;

    /** @var string */
    private $originComponentId;

    /** @var string */
    private $destinationComponentId;

    /** @var string */
    private $configurationId;

    public function setUp() : void
    {
        parent::setUp();
        $this->storageApiClient = new Client(['token' => getenv('KBC_TOKEN'), 'url' => getenv('KBC_URL')]);
        $this->components = new Components($this->storageApiClient);

        $this->originComponentId = 'keboola.ex-google-analytics-v4';
        $this->destinationComponentId = 'keboola.ex-google-analytics-v6';

        $this->configurationId = uniqid('migrationtest');
        $c = new Configuration();
        $c->setComponentId($this->originComponentId);
        $c->setConfigurationId($this->configurationId);
        $c->setName($this->configurationId);
        $c->setDescription('Migrate this account');
        $config = [
            "authorization" => [
                "oauth_api" => [
                    "id" => "703587412",
                    "version" => 3,
                ],
            ],
            "parameters" => [
                "profiles" => [
                    [
                        "accountId" => "128209249",
                        "webPropertyId" => "654321",
                        "webPropertyName" => "status.keboola.com",
                        "accountName" => "Keboola Status Blog",
                        "name" => "All Web Site Data",
                        "id" => "88156763",
                    ],
                ],
                "outputBucket" => "in.c-keboola-ex-google-analytics-v4-703587412",
                "queries" => [
                    [
                        "name" => "qwer",
                        "enabled" => true,
                        "outputTable" => "asdqwe",
                        "endpoint" => "reports",
                        "query" => [
                            "dateRanges" => [
                                [
                                    "startDate" => "-4 days",
                                    "endDate" => "today",
                                ],
                            ],
                            "metrics" => [
                                [
                                    "expression" => "ga:users",
                                ],
                            ],
                            "dimensions" => [
                                [
                                    "name" => "ga:date",
                                ],
                            ],
                        ],
                        "id" => 3132,
                    ],
                    [
                        "id" => 59574,
                        "name" => "asdefwe",
                        "enabled" => true,
                        "outputTable" => "asdefwe",
                        "endpoint" => "mcf",
                        "query" => [
                            "dateRanges" => [
                                [
                                    "startDate" => "-4 days",
                                    "endDate" => "today",
                                ],
                            ],
                            "viewId" => "88156763",
                            "metrics" => [
                                [
                                    "expression" => "mcf:firstImpressionConversions",
                                ],
                            ],
                            "dimensions" => [
                                [
                                    "name" => "mcf:sourcePath",
                                ],
                            ],
                            "filtersExpression" => "askjfwbenf",
                        ],
                        "antisampling" => "dailyWalk",
                    ],
                ],
            ],
        ];
        $c->setConfiguration($config);
        $this->components->addConfiguration($c);
    }

    public function testExecute() : void
    {
        $migration = new KeboolaExGoogleAnalyticsV4Migration(new Logger(APP_NAME));
        $migration
            ->setOriginComponentId($this->originComponentId)
            ->setDestinationComponentId($this->destinationComponentId);

        $createdConfigurations = $migration->execute();
        $this->assertNotEmpty($createdConfigurations);
        $this->assertCount(1, $createdConfigurations);

        $originConfig1 = $this->components->getConfiguration($this->originComponentId, $this->configurationId);
        $this->assertArrayHasKey('migrationStatus', $originConfig1['configuration']['runtime']);
        $this->assertEquals('success', $originConfig1['configuration']['runtime']['migrationStatus']);
        $destConfig1 = $this->components->getConfiguration($this->destinationComponentId, $this->configurationId);
        $this->assertNotEmpty($destConfig1);
        $this->assertArrayNotHasKey('queries', $destConfig1['configuration']['parameters']);
        $this->assertArrayNotHasKey('outputBucket', $destConfig1['configuration']['parameters']);
        $this->assertArrayHasKey('rows', $destConfig1);
        $this->assertCount(2, $destConfig1['rows']);
        $this->assertArrayHasKey('query', $destConfig1['rows'][0]['configuration']['parameters']);
        $this->assertArrayHasKey('endpoint', $destConfig1['rows'][0]['configuration']['parameters']);
        $this->assertArrayHasKey('outputTable', $destConfig1['rows'][0]['configuration']['parameters']);
        $this->assertArrayNotHasKey('antisampling', $destConfig1['rows'][0]['configuration']['parameters']);
        $this->assertArrayHasKey('antisampling', $destConfig1['rows'][1]['configuration']['parameters']);
    }

    public function tearDown() : void
    {
        parent::tearDown();
        $this->components->deleteConfiguration($this->originComponentId, $this->configurationId);
        $this->components->deleteConfiguration($this->destinationComponentId, $this->configurationId);
    }
}
