<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\KeboolaGoogleBigQueryWriterMigration;
use Keboola\ConfigMigrationTool\Migration\SalesforceExtractorV2Migration;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class SalesforceV2ExtractorMigrationTest extends TestCase
{
    /** @var SalesforceExtractorV2Migration */
    private $migration;

    public function setUp(): void
    {
        parent::setUp();
        $logger = new Logger('config-migration-tool', [
            new \Keboola\ConfigMigrationTool\Logger\InfoHandler(),
            new \Monolog\Handler\StreamHandler('php://stderr', Logger::NOTICE),
        ]);
        $this->migration = new SalesforceExtractorV2Migration($logger);
    }

    public function testTransformRowSimple(): void
    {
        $configuration = new Configuration();
        $configuration->setName("Test");
        $configuration->setConfigurationId("12345678");
        $configuration->setConfiguration([
            'parameters' => [
                'loginname' => 'loginname',
                '#password' => 'password',
                '#securitytoken' => 'securitytoken',
                'sandbox' => false,
            ],
        ]);

        $state = [
            "component" => [
                "bulkRequests" => [
                    "Contact" => 1624960301752,
                ],
            ],
            "storage" => [
                "input" => [
                    "tables" => [],
                    "files" => [],
                ],
            ],
        ];
        $row = [
            "id" => "12345",
            "name" => "test_row",
            "description" => "test_row",
            "isDisabled" => false,
            "configuration" => [
                "parameters" => [
                    "sinceLast" => false,
                    "objects" => [
                        [
                            "name" => "Contact",
                            "soql" => "select Id, FirstnAme,LastName,isdeleted,lastmodifieddate from Contact ",
                        ],
                    ],
                ],
            ],
            'state' => $state,
        ];

        $rowCfg = $this->buildConfigurationRowObjects($configuration, [$row]);
        $result = $this->migration->transformConfiguration($configuration, $rowCfg);

        $this->assertEquals([
            'parameters' => [
                'username' => 'loginname',
                '#password' => 'password',
                '#security_token' => 'securitytoken',
                'sandbox' => false,
                'api_version' => '39.0',
            ],
        ], $result['configuration']->getConfiguration());

        // row equals
        $this->assertEquals([
            "parameters" => [
                "is_deleted" => false,
                "bucket_name" => "htns-ex-salesforce-12345678",
                "soql_query" => "select Id, FirstnAme,LastName,isdeleted,lastmodifieddate from Contact ",
                "query_type_selector" => "Custom SOQL",
                "loading_options" => [
                    "pkey" => [],
                    "incremental" => 0,
                ],
            ],
        ], $result['rows'][0]->getConfiguration());

        // state equals

        $this->assertEquals($state, $result['rows'][0]->getState());
    }

    public function testTransformRowObjectIncremental(): void
    {
        $configuration = new Configuration();
        $configuration->setName("Test");
        $configuration->setConfigurationId("699408424");
        $configuration->setConfiguration([
            'parameters' => [
                'loginname' => 'loginname',
                '#password' => 'password',
                '#securitytoken' => 'securitytoken',
                'sandbox' => false,
            ],
        ]);

        $state = [
            "component" => [
                "bulkRequests" => [
                    "Contact" => 1625145787098,
                ],
            ],
            "storage" => [
                "input" => [
                    "tables" => [],
                    "files" => [],
                ],
            ],
        ];
        $row = [
            "id" => "12345",
            "name" => "test_row",
            "description" => "test_row",
            "isDisabled" => false,
            "configuration" => [
                "parameters" => [
                    "sinceLast" => true,
                    "objects" => [
                        [
                            "name" => "Contact",
                            "soql" => "",
                        ],
                    ],
                ],
            ],
            'state' => $state,
        ];

        $new_state = [
            "component" => [
                "last_run" => "2021-07-01T13:23:07.000Z",
                "prev_output_columns" => [],
            ],
            "storage" => [
                "input" => [
                    "tables" => [],
                    "files" => [],
                ],
            ],
        ];

        $rowCfg = $this->buildConfigurationRowObjects($configuration, [$row]);
        $result = $this->migration->transformConfiguration($configuration, $rowCfg);

        $this->assertEquals([
            'parameters' => [
                'username' => 'loginname',
                '#password' => 'password',
                '#security_token' => 'securitytoken',
                'sandbox' => false,
                'api_version' => '39.0',
            ],
        ], $result['configuration']->getConfiguration());

        // row equals
        $this->assertEquals([
            "parameters" => [
                "is_deleted" => false,
                "bucket_name" => "htns-ex-salesforce-699408424",
                "query_type_selector" => "Object",
                "object" => "Contact",
                "loading_options" => [
                    "pkey" => ["Id"],
                    "incremental" => 1,
                    "incremental_fetch" => true,
                    "incremental_field" => "LastModifiedDate",
                ],
            ],
        ], $result['rows'][0]->getConfiguration());

        // state equals

        $this->assertEquals($new_state, $result['rows'][0]->getState());
    }

    public function testSampleConfiguration(): void
    {
        print_r(dirname(dirname(__FILE__)));
        $parentDir = dirname(dirname(__FILE__));
        $testConfig = $this->getJson($parentDir . '/resources/SalesforceV2ExtractorMigration/test_config.json');
        $testConfigMigrated = $this->getJson($parentDir .
            '/resources/SalesforceV2ExtractorMigration/test_config_migrated.json');

        $configuration = new Configuration();
        $configuration->setName($testConfig['name']);
        $configuration->setConfigurationId($testConfig['id']);
        $configuration->setConfiguration($testConfig['configuration']);

        $rows = $this->buildConfigurationRowObjects($configuration, $testConfig['rows']);

        $result = $this->migration->transformConfiguration($configuration, $rows);

        $this->assertEquals(
            $testConfigMigrated['configuration'],
            $result['configuration']->getConfiguration()
        );

        foreach ($result['rows'] as $index => $migrRow) {
            $this->assertEquals(
                $testConfigMigrated['rows'][$index]['configuration'],
                $result['rows'][$index]->getConfiguration()
            );
        }
    }

    protected function getJson(string $path): array
    {
        $string = file_get_contents($path);
        return json_decode($string, true);
    }

    protected function buildConfigurationRowObjects(Configuration $configurationObject, array $rows): array
    {
        $row_objects = [];
        if (!empty($rows)) {
            foreach ($rows as $r) {
                $rowObject = new ConfigurationRow($configurationObject);

                $rowObject->setRowId($r['id']);
                $rowObject->setConfiguration($r['configuration']);
                $rowObject->setName($r['name']);
                $rowObject->setDescription($r['description']);
                $rowObject->setIsDisabled($r['isDisabled']);
                $rowObject->setState($r['state']);
                $rowObject->setState($r['state']);
                $row_objects[] = $rowObject;
            }
        }
        return $row_objects;
    }
}
