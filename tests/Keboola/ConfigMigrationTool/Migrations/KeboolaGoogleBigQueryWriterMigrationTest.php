<?php

/** @noinspection PhpParamsInspection */

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\KeboolaGoogleBigQueryWriterMigration;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class KeboolaGoogleBigQueryWriterMigrationTest extends TestCase
{

    public const DEPRECATED_COMPONENT_ID = 'keboola.wr-google-bigquery';
    public const REPLACEMENT_COMPONENT_ID = 'keboola.wr-google-bigquery-v2';

    public function testTransformConfiguration(): void
    {
        $currentConfig = [
            'id' => 'c1',
            'configuration' => [
                'authorization' => [
                    'oauth_api' => [
                        'id' => '1',
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

        $migration = new KeboolaGoogleBigQueryWriterMigration(new Logger(APP_NAME));
        $result = $migration->transformConfiguration($currentConfig);

        $this->assertEquals([
            'parameters' => [
                'dataset' => 'travis_test',
            ],
        ], $result['configuration']);
        $this->assertEquals($currentConfig['rows'][0]['configuration'], $result['rows'][0]['configuration']);
        $this->assertEquals($currentConfig['rows'][1]['configuration'], $result['rows'][1]['configuration']);
    }
}
