<?php

/** @noinspection PhpParamsInspection */

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\KeboolaGoogleBigQueryWriterMigration;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class KeboolaGoogleBigQueryWriterMigrationTest extends TestCase
{
    /** @var array */
    private $oldConfig;
    /** @var string */
    private $originComponentId;
    /** @var string */
    private $destinationComponentId;
    /** @var Client */
    private $storageApiClient;
    /** @var Components */
    private $components;

    public function setUp(): void
    {
        parent::setUp();

        $this->originComponentId = 'keboola.wr-google-bigquery';
        $this->destinationComponentId = 'keboola.wr-google-bigquery-v2';

        $configId = uniqid('migrationtest-keboola-google-biguery-writer-');
        $this->oldConfig = [
            'id' => $configId,
            'configuration' => [
                'authorization' => [
                    'oauth_api' => [
                        'id' => '415775277',
                    ],
                ],
                'parameters' => [
                    'project' => 'bigquery-writer-158018',
                    'dataset' => 'travis_test',
                ],
            ],
            'rows' => [
                [
                    'id' => 't1',
                    'configuration' => [
                        'storage' => [
                            'input' => [
                                'tables' => [
                                    [
                                        'source' => 'in.c-data.source1',
                                        'destination' => 'source1.csv',
                                    ],
                                ],
                            ],
                        ],
                        'parameters' => [
                            'tables' => [
                                0 => [
                                    'dbName' => 'test1',
                                    'tableId' => 'in.c-data.source1',
                                    'items' => [
                                        'name' => 'col1',
                                        'dbName' => 'col1',
                                        'type' => 'STRING',
                                    ],
                                    [
                                        'name' => 'col2',
                                        'dbName' => 'col2',
                                        'type' => 'INTEGER',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 't2',
                    'configuration' => [
                        'storage' => [
                            'input' => [
                                'tables' => [
                                    [
                                        'source' => 'in.c-data.source2',
                                        'destination' => 'source2.csv',
                                    ],
                                ],
                            ],
                        ],
                        'parameters' => [
                            'tables' => [
                                0 => [
                                    'dbName' => 'test2',
                                    'tableId' => 'in.c-data.source2',
                                    'items' => [
                                        'name' => 'col1',
                                        'dbName' => 'col1',
                                        'type' => 'STRING',
                                    ],
                                    [
                                        'name' => 'col2',
                                        'dbName' => 'col2',
                                        'type' => 'INTEGER',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->storageApiClient = new Client(['token' => getenv('KBC_TOKEN'), 'url' => getenv('KBC_URL')]);
        $this->components = new Components($this->storageApiClient);

        $c = new Configuration();
        $c->setComponentId($this->originComponentId);
        $c->setConfigurationId($this->oldConfig['id']);
        $c->setName($this->oldConfig['id']);
        $c->setDescription('Migrate this account');
        $c->setConfiguration($this->oldConfig['configuration']);
        $this->components->addConfiguration($c);

        $r = new ConfigurationRow($c);
        $r->setRowId('t1');
        $r->setConfiguration($this->oldConfig['rows'][0]['configuration']);
        $this->components->addConfigurationRow($r);

        $r = new ConfigurationRow($c);
        $r->setRowId('t2');
        $r->setConfiguration($this->oldConfig['rows'][1]['configuration']);
        $this->components->addConfigurationRow($r);
    }

    public function testTransformConfiguration(): void
    {
        $migration = new KeboolaGoogleBigQueryWriterMigration(new Logger(APP_NAME));
        $result = $migration->transformConfiguration($this->oldConfig);

        $this->assertEquals([
            'parameters' => [
                'dataset' => 'travis_test',
            ],
        ], $result['configuration']);
        $this->assertEquals($this->oldConfig['rows'][0]['configuration'], $result['rows'][0]['configuration']);
        $this->assertEquals($this->oldConfig['rows'][1]['configuration'], $result['rows'][1]['configuration']);
    }
}
