<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 23/05/16
 * Time: 10:36
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Migration\ExGoogleAnalyticsMigration;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;

class ExGoogleAnalyticsMigrationTest extends ExGoogleAnalyticsTest
{
    public function testExecute()
    {
        $testConfigIds = $this->createOldConfigs();
        $migration = new ExGoogleAnalyticsMigration($this->getLogger());
        $createdConfigurations = $migration->execute();

        /** @var Configuration $configuration */
        foreach ($createdConfigurations as $configuration) {
            $this->assertEquals('keboola.ex-google-analytics-v4', $configuration->getComponentId());
            $config = $configuration->getConfiguration();
            $this->assertArrayHasKey('authorization', $config);
            $this->assertArrayHasKey('parameters', $config);
            $parameters = $config['parameters'];
            $this->assertArrayHasKey('outputBucket', $parameters);
            $this->assertArrayHasKey('queries', $parameters);
            $queries = $parameters['queries'];
            foreach ($queries as $query) {
                $this->assertArrayHasKey('id', $query);
                $this->assertArrayHasKey('name', $query);
                $this->assertArrayHasKey('query', $query);
                $this->assertArrayHasKey('outputTable', $query);
                $this->assertArrayHasKey('enabled', $query);
                $this->assertArrayHasKey('metrics', $query['query']);
                $metric = $query['query']['metrics'][0];
                $this->assertArrayHasKey('expression', $metric);
                $this->assertArrayHasKey('query', $query);
                $this->assertArrayHasKey('dimensions', $query['query']);
                $dimension = $query['query']['dimensions'][0];
                $this->assertArrayHasKey('name', $dimension);
                $this->assertArrayHasKey('viewId', $query['query']);
                $this->assertArrayHasKey('dateRanges', $query['query']);
            }
        }

        //@todo: clear created configurations
    }

    private function getLogger()
    {
        return new Logger(APP_NAME, [
            new \Keboola\ConfigMigrationTool\Logger\InfoHandler(),
            new \Monolog\Handler\StreamHandler('php://stderr', Logger::NOTICE)
        ]);
    }
}
