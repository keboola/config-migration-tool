<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\KeboolaGoogleBigQueryWriterMigration;
use Keboola\StorageApi\Options\Components\Configuration;
use PHPUnit\Framework\TestCase;

class KeboolaGoogleBigQueryWriterMigrationTest extends TestCase
{
    public function testTransform(): void
    {
        $configuration = new Configuration();
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

        $result = KeboolaGoogleBigQueryWriterMigration::transform($configuration);

        $this->assertEquals([
            'authorization' => [
                'oauth_api' => [
                    'id' => '1',
                ],
            ],
            'parameters' => [
                'dataset' => 'travis_test',
            ],
        ], $result->getConfiguration());
    }
}
