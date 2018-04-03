<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\KeboolaGoodDataWriterMigration;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class KeboolaGoodDataWriterMigrationTest extends TestCase
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

        $this->originComponentId = 'gooddata-writer';
        $this->destinationComponentId = 'keboola.gooddata-writer';

        $this->configurationId = uniqid('migrationtest-wrgd-');
        $c = new Configuration();
        $c->setComponentId($this->originComponentId);
        $c->setConfigurationId($this->configurationId);
        $c->setName($this->configurationId);
        $c->setDescription('Migrate this account');
        $c->setConfiguration(
            [
                'user' => [
                    'login' => uniqid(),
                    'password' => uniqid(),
                ],
                'project' => [
                    'pid' => uniqid(),
                ],
                'dimensions' => [
                    'd1' => [
                        'title' => uniqid(),
                        'includeTime' => true,
                        'isExported' => true,
                    ],
                ],
            ]
        );
        $this->components->addConfiguration($c);

        $r = new ConfigurationRow($c);
        $r->setRowId('t1');
        $r->setConfiguration([
            'title' => uniqid(),
            'export' => true,
            'isExported' => true,
            'grain' => 'c1,c2,c3',
            'ignoreFilter' => true,
            'anchorIdentifier' => uniqid(),
            'columns' => [
                'c1' => [
                    'type' => 'ATTRIBUTE',
                    'title' => uniqid(),
                    'identifier' => uniqid(),
                    'sortLabel' => 'c3',
                    'sortOrder' => 'DESC',
                ],
                'c2' => [
                    'type' => 'FACT',
                    'title' => uniqid(),
                    'dataType' => 'DECIMAL',
                    'dataTypeSize' => '8,3',
                ],
                'c3' => [
                    'type' => 'LABEL',
                    'title' => uniqid(),
                    'reference' => 'c1',
                ],
                'c4' => [
                    'type' => 'DATE',
                    'format' => 'yyyy-MM-dd HH:mm:ss',
                    'dateDimension' => 'd1',
                ],
                'c5' => [
                    'type' => 'REFERENCE',
                    'schemaReference' => 't2',
                ],
            ],
        ]);
        $this->components->addConfigurationRow($r);

        $r = new ConfigurationRow($c);
        $r->setRowId('t2');
        $r->setConfiguration([
            'title' => uniqid(),
            'columns' => [
                'c21' => [
                    'type' => 'CONNECTION_POINT',
                    'title' => uniqid(),
                ],
                'c22' => [
                    'type' => 'FACT',
                    'title' => uniqid(),
                ],
            ],
        ]);
        $this->components->addConfigurationRow($r);
    }

    public function testExecute() : void
    {
        $migration = new KeboolaGoodDataWriterMigration(new Logger(APP_NAME));
        $migration
            ->setOriginComponentId($this->originComponentId)
            ->setDestinationComponentId($this->destinationComponentId);

        $createdConfigurations = $migration->execute();
        $this->assertNotEmpty($createdConfigurations);
        $this->assertGreaterThanOrEqual(1, count($createdConfigurations));

        $originConfig1 = $this->components->getConfiguration($this->originComponentId, $this->configurationId);
        $this->assertArrayHasKey('migrationStatus', $originConfig1['configuration']);
        $this->assertEquals('success', $originConfig1['configuration']['migrationStatus']);
        $destConfig1 = $this->components->getConfiguration($this->destinationComponentId, $this->configurationId);
        $this->assertNotEmpty($destConfig1);
        $this->assertArrayHasKey('migrationStatus', $originConfig1['configuration']);
        $this->assertArrayHasKey('user', $destConfig1['configuration']['parameters']);
        $this->assertArrayNotHasKey('password', $destConfig1['configuration']['parameters']['user']);
        $this->assertArrayHasKey('#password', $destConfig1['configuration']['parameters']['user']);
        $this->assertArrayHasKey('login', $destConfig1['configuration']['parameters']['user']);
        $this->assertArrayHasKey('project', $destConfig1['configuration']['parameters']);
        $this->assertArrayHasKey('pid', $destConfig1['configuration']['parameters']['project']);
    }

    public function tearDown() : void
    {
        parent::tearDown();
        $this->components->deleteConfiguration($this->originComponentId, $this->configurationId);
        $this->components->deleteConfiguration($this->destinationComponentId, $this->configurationId);
    }
}
