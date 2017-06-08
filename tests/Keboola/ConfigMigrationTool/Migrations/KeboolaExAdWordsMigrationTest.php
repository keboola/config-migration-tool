<?php
/**
 * @copy Keboola 2016
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\KeboolaExAdWordsMigration;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;

class KeboolaExAdWordsMigrationTest extends \PHPUnit_Framework_TestCase
{
    /** @var Client */
    private $storageApiClient;

    /** @var Components */
    private $components;

    private $originComponentId;
    private $destinationComponentId;
    private $configurationId;

    public function setUp()
    {
        parent::setUp();
        $this->storageApiClient = new Client(['token' => getenv('KBC_TOKEN')]);
        $this->components = new Components($this->storageApiClient);

        $this->originComponentId = 'ex-adwords-v2';
        $this->destinationComponentId = 'keboola.ex-adwords-v201702';

        $this->configurationId = uniqid('migrationtest');
        $c = new Configuration();
        $c->setComponentId($this->originComponentId);
        $c->setConfigurationId($this->configurationId);
        $c->setName($this->configurationId);
        $c->setDescription('Migrate this account');
        $c->setConfiguration(['parameters' =>
            ['a' => uniqid(), 'developer_token' => 'dT', 'c' => uniqid(), 'customer_id' => 'cId', 'bucket' => 'b']
        ]);
        $this->components->addConfiguration($c);
    }

    public function testExecute()
    {
        $migration = new KeboolaExAdWordsMigration(new Logger(APP_NAME));
        $migration
            ->setOriginComponentId($this->originComponentId)
            ->setDestinationComponentId($this->destinationComponentId);

        $createdConfigurations = $migration->execute();
        $this->assertNotEmpty($createdConfigurations);
        $this->assertCount(1, $createdConfigurations);

        $originConfig1 = $this->components->getConfiguration($this->originComponentId, $this->configurationId);
        $this->assertArrayHasKey('migrationStatus', $originConfig1['configuration']);
        $this->assertEquals('success', $originConfig1['configuration']['migrationStatus']);
        $destConfig1 = $this->components->getConfiguration($this->destinationComponentId, $this->configurationId);
        $this->assertNotEmpty($destConfig1);
        $this->assertArrayHasKey('migrationStatus', $originConfig1['configuration']);
        $this->assertArrayNotHasKey('developer_token', $destConfig1['configuration']['parameters']);
        $this->assertArrayHasKey('#developerToken', $destConfig1['configuration']['parameters']);
        $this->assertStringStartsWith(
            'KBC::ComponentProjectEncrypted',
            $destConfig1['configuration']['parameters']['#developerToken']
        );
        $this->assertArrayNotHasKey('bucket', $destConfig1['configuration']['parameters']);
        $this->assertArrayNotHasKey('customer_id', $destConfig1['configuration']['parameters']);
        $this->assertArrayHasKey('customerId', $destConfig1['configuration']['parameters']);
        $this->assertEquals('cId', $destConfig1['configuration']['parameters']['customerId']);
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->components->deleteConfiguration($this->originComponentId, $this->configurationId);
        $this->components->deleteConfiguration($this->destinationComponentId, $this->configurationId);
    }
}
