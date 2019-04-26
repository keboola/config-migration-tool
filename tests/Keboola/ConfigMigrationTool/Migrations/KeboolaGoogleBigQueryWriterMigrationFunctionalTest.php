<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\KeboolaGoogleBigQueryWriterMigration;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Keboola\StorageApi\Options\Components\ListComponentConfigurationsOptions;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class KeboolaGoogleBigQueryWriterMigrationFunctionalTest extends TestCase
{

    public const DEPRECATED_COMPONENT_ID = 'keboola.wr-google-bigquery';
    public const REPLACEMENT_COMPONENT_ID = 'keboola.wr-google-bigquery-v2';

    public function testDoExecute(): void
    {
        $storageApiClient = new Client(['token' => getenv('KBC_TOKEN'), 'url' => getenv('KBC_URL')]);
        $components = new Components($storageApiClient);

        $configId = uniqid('migrationtest-keboola-google-biguery-writer-');

        $configuration = new Configuration();
        $configuration->setComponentId('keboola.wr-google-bigquery');
        $configuration->setConfigurationId($configId);
        $configuration->setName($configId);
        $configuration->setDescription('Migrate this account');
        $configuration->setConfiguration([
            'authorization' => [
                'oauth_api' => [
                    'id' => '1',
                ],
            ],
            'parameters' => [
                'project' => 'bigquery-writer-158018',
                'dataset' => 'travis_test',
            ],
        ]);
        $components->addConfiguration($configuration);

        $row1 = new ConfigurationRow($configuration);
        $row1->setRowId('r1');
        $row1->setConfiguration([
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
        ]);
        $components->addConfigurationRow($row1);

        $row2 = new ConfigurationRow($configuration);
        $row2->setRowId('r2');
        $row2->setConfiguration([
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
        ]);
        $components->addConfigurationRow($row2);

        $logger = new Logger(APP_NAME);
        $logger->setHandlers([new NullHandler()]);
        $migration = new KeboolaGoogleBigQueryWriterMigration($logger);
        $migration
            ->setOriginComponentId(self::DEPRECATED_COMPONENT_ID)
            ->setDestinationComponentId(self::REPLACEMENT_COMPONENT_ID);
        $migration->execute();

        $configurations = $components->listComponentConfigurations(
            (new ListComponentConfigurationsOptions())->setComponentId(self::REPLACEMENT_COMPONENT_ID)
        );
        $this->assertCount(1, $configurations);
        $this->assertEquals($configId, $configurations[0]['id']);

        $configuration = $components->getConfiguration(self::REPLACEMENT_COMPONENT_ID, $configurations[0]['id']);
        $configurationData = $configuration['configuration'];
        $this->assertStringContainsString('KBC::ProjectSecure::', $configurationData['parameters']['service_account']['#private_key']);
        $configurationData['parameters']['service_account']['#private_key'] = '';
        $this->assertEquals([
            'parameters' => [
                'service_account' => [
                    '#private_key' => '',
                    'project_id' => '',
                    'token_uri' => '',
                    'client_email' => '',
                    'client_id' => '',
                    'auth_uri' => '',
                    'auth_provider_x509_cert_url' => '',
                    'private_key_id' => '',
                    'client_x509_cert_url' => '',
                    'type' => '',
                ],
                'dataset' => 'travis_test',
            ],
        ], $configurationData);

        $this->assertCount(2, $configuration['rows']);

        $this->assertEquals('r1', $configuration['rows'][0]['id']);
        $this->assertEquals($row1->getConfiguration(), $configuration['rows'][0]['configuration']);

        $this->assertEquals('r2', $configuration['rows'][1]['id']);
        $this->assertEquals($row2->getConfiguration(), $configuration['rows'][1]['configuration']);
    }

    public function tearDown() : void
    {
        parent::tearDown();
        $storageApiClient = new Client(['token' => getenv('KBC_TOKEN'), 'url' => getenv('KBC_URL')]);
        $components = new Components($storageApiClient);

        $configurations = $components->listComponentConfigurations(
            (new ListComponentConfigurationsOptions())->setComponentId(self::DEPRECATED_COMPONENT_ID)
        );
        foreach ($configurations as $configuration) {
            $components->deleteConfiguration(self::DEPRECATED_COMPONENT_ID, $configuration['id']);
        }

        $configurations = $components->listComponentConfigurations(
            (new ListComponentConfigurationsOptions())->setComponentId(self::REPLACEMENT_COMPONENT_ID)
        );
        foreach ($configurations as $configuration) {
            $components->deleteConfiguration(self::REPLACEMENT_COMPONENT_ID, $configuration['id']);
        }
    }
}
