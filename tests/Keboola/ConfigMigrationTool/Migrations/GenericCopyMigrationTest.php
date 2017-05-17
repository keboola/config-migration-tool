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
use Monolog\Logger;

class GenericCopyMigrationTest extends \PHPUnit_Framework_TestCase
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
        $this->assertArrayHasKey('migrationStatus', $originConfig1['configuration']);
        $destConfig1 = $this->components->getConfiguration($this->destinationComponentId, $this->configurationId1);
        $this->assertNotEmpty($destConfig1);
        unset($originConfig1['configuration']['migrationStatus']);
        $this->assertEquals($originConfig1['configuration'], $destConfig1['configuration']);

        $originConfig2 = $this->components->getConfiguration($this->originComponentId, $this->configurationId2);
        $this->assertArrayHasKey('migrationStatus', $originConfig2['configuration']);
        $destConfig2 = $this->components->getConfiguration($this->destinationComponentId, $this->configurationId2);
        $this->assertNotEmpty($destConfig2);
        unset($originConfig2['configuration']['migrationStatus']);
        $this->assertEquals($originConfig2['configuration'], $destConfig2['configuration']);
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
