<?php
/**
 * @copy Keboola 2016
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\GenericCopyMigration;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class GenericCopyMigrationTest extends TestCase
{
    /** @var Client */
    private $storageApiClient;

    /** @var Components */
    private $components;

    private $originComponentId;
    private $destinationComponentId;
    private $configurationId1;
    private $configurationId2;

    public function setUp()
    {
        parent::setUp();
        $this->storageApiClient = new Client(['token' => getenv('KBC_TOKEN')]);
        $this->components = new Components($this->storageApiClient);

        $this->originComponentId = 'ex-adwords-v2';
        $this->destinationComponentId = 'keboola.ex-adwords-v201702';

        $this->configurationId1 = $this->createTestConfiguration($this->originComponentId);
        $this->configurationId2 = $this->createTestConfiguration($this->originComponentId);
    }

    protected function createTestConfiguration($componentId)
    {
        $id = uniqid('migrationtest');

        $c = new Configuration();
        $c->setComponentId($componentId);
        $c->setConfigurationId($id);
        $c->setName($id);
        $c->setDescription('Migrate this account');
        $c->setConfiguration(['a' => uniqid(), 'b' => uniqid(), 'c' => uniqid()]);
        $this->components->addConfiguration($c);

        $row = new ConfigurationRow($c);
        $row->setRowId(uniqid())->setConfiguration(['x' => uniqid(), 'y' => uniqid()]);
        $this->components->addConfigurationRow($row);

        $row = new ConfigurationRow($c);
        $row->setRowId(uniqid())->setConfiguration(['x' => uniqid(), 'y' => uniqid()]);
        $this->components->addConfigurationRow($row);

        return $id;
    }

    public function testExecute()
    {
        $migration = new GenericCopyMigration(new Logger(APP_NAME));
        $migration
            ->setOriginComponentId($this->originComponentId)
            ->setDestinationComponentId($this->destinationComponentId);

        $createdConfigurations = $migration->execute();
        $this->assertNotEmpty($createdConfigurations);
        $this->assertCount(2, $createdConfigurations);

        $originConfig1 = $this->components->getConfiguration($this->originComponentId, $this->configurationId1);
        $destConfig1 = $this->components->getConfiguration($this->destinationComponentId, $this->configurationId1);
        $this->runConfigurationTest($originConfig1, $destConfig1);

        $originConfig2 = $this->components->getConfiguration($this->originComponentId, $this->configurationId2);
        $destConfig2 = $this->components->getConfiguration($this->destinationComponentId, $this->configurationId2);
        $this->runConfigurationTest($originConfig2, $destConfig2);
    }

    protected function runConfigurationTest($originConfig, $destConfig)
    {
        $this->assertArrayHasKey('migrationStatus', $originConfig['configuration']);
        $this->assertEquals('success', $originConfig['configuration']['migrationStatus']);
        $this->assertNotEmpty($destConfig);
        unset($originConfig['configuration']['migrationStatus']);
        $this->assertEquals($originConfig['configuration'], $destConfig['configuration']);
        $this->assertCount(2, $originConfig['rows']);
        $this->assertCount(2, $destConfig['rows']);
        foreach ($originConfig['rows'] as $i => $r) {
            $this->assertEquals($r['id'], $destConfig['rows'][$i]['id']);
            $this->assertEquals($r['configuration'], $destConfig['rows'][$i]['configuration']);
        }
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->components->deleteConfiguration($this->originComponentId, $this->configurationId1);
        $this->components->deleteConfiguration($this->originComponentId, $this->configurationId2);
        $this->components->deleteConfiguration($this->destinationComponentId, $this->configurationId1);
        $this->components->deleteConfiguration($this->destinationComponentId, $this->configurationId2);
    }
}
