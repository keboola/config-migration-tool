<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\MetaComponentsMigration;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class MetaComponentsMigrationTest extends TestCase
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
    private $configurationId1;

    /** @var string */
    private $configurationId2;

    public function setUp(): void
    {
        parent::setUp();
        $this->storageApiClient = new Client(['token' => getenv('KBC_TOKEN'), 'url' => getenv('KBC_URL')]);
        $this->components = new Components($this->storageApiClient);

        $this->originComponentId = 'keboola.ex-facebook';
        $this->destinationComponentId = 'keboola.ex-facebook-pages';

        $this->configurationId1 = $this->createTestConfiguration($this->originComponentId);
        $this->configurationId2 = $this->createTestConfiguration($this->originComponentId);
    }

    protected function createTestConfiguration(string $componentId): string
    {
        $id = uniqid('metacompmigrationtest');

        $c = new Configuration();
        $c->setComponentId($componentId);
        $c->setConfigurationId($id);
        $c->setName($id);
        $c->setDescription('Test Meta Components Migration');
        $c->setConfiguration([
            'parameters' => [
                'test_param' => 'test_value',
            ],
            'other_config' => 'other_value',
        ]);
        $this->components->addConfiguration($c);

        $row = new ConfigurationRow($c);
        $row->setRowId(uniqid())->setConfiguration(['x' => uniqid(), 'y' => uniqid()]);
        $this->components->addConfigurationRow($row);

        return $id;
    }

    public function testExecute(): void
    {
        $migration = new MetaComponentsMigration(new Logger(APP_NAME));
        $migration
            ->setOriginComponentId($this->originComponentId)
            ->setDestinationComponentId($this->destinationComponentId);

        $createdConfigurations = $migration->execute();
        $this->assertNotEmpty($createdConfigurations);
        $this->assertCount(2, $createdConfigurations);

        $originConfig1 = $this->components->getConfiguration($this->originComponentId, $this->configurationId1);
        $destConfig1 = $this->components->getConfiguration($this->destinationComponentId, $this->configurationId1);
        $this->runConfigurationTest($originConfig1, $destConfig1, $this->configurationId1);

        $originConfig2 = $this->components->getConfiguration($this->originComponentId, $this->configurationId2);
        $destConfig2 = $this->components->getConfiguration($this->destinationComponentId, $this->configurationId2);
        $this->runConfigurationTest($originConfig2, $destConfig2, $this->configurationId2);
    }

    protected function runConfigurationTest(array $originConfig, array $destConfig, string $configurationId): void
    {
        // Kontrola, že migrace byla označena jako úspěšná
        $this->assertArrayHasKey('migrationStatus', $originConfig['configuration']['runtime']);
        $this->assertEquals('success', $originConfig['configuration']['runtime']['migrationStatus']);
        
        // Kontrola, že byla přidána custom-bucket parametr
        $this->assertArrayHasKey('custom-bucket', $destConfig['configuration']['parameters']);
        $expectedComponentId = str_replace(['_', '.'], '-', $this->destinationComponentId);
        $expectedConfigurationId = str_replace(['_', '.'], '-', $configurationId);
        $expectedCustomBucket = 'c-' . $expectedComponentId . '-' . $expectedConfigurationId;
        $this->assertEquals($expectedCustomBucket, $destConfig['configuration']['parameters']['custom-bucket']);
        
        // Kontrola, že ostatní parametry zůstaly zachovány
        $this->assertArrayHasKey('test_param', $destConfig['configuration']['parameters']);
        $this->assertEquals('test_value', $destConfig['configuration']['parameters']['test_param']);
        
        // Kontrola, že ostatní části konfigurace zůstaly zachovány
        $this->assertEquals($originConfig['configuration']['other_config'], $destConfig['configuration']['other_config']);
        
        // Kontrola řádků
        $this->assertCount(1, $originConfig['rows']);
        $this->assertCount(1, $destConfig['rows']);
        foreach ($originConfig['rows'] as $i => $r) {
            $this->assertEquals($r['id'], $destConfig['rows'][$i]['id']);
            $this->assertEquals($r['configuration'], $destConfig['rows'][$i]['configuration']);
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->components->deleteConfiguration($this->originComponentId, $this->configurationId1);
        $this->components->deleteConfiguration($this->originComponentId, $this->configurationId2);
        try {
            $this->components->deleteConfiguration($this->destinationComponentId, $this->configurationId1);
        } catch (\Throwable $e) {
        }
        try {
            $this->components->deleteConfiguration($this->destinationComponentId, $this->configurationId2);
        } catch (\Throwable $e) {
        }
    }
}
