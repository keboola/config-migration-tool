<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 23/05/16
 * Time: 10:36
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Migration\ExGoogleAnalyticsMigration;
use Monolog\Logger;

class ExGoogleAnalyticsMigrationTest extends ExGoogleAnalyticsTest
{
    public function testExecute()
    {
        $testConfigIds = $this->createOldConfigs();

        $migration = new ExGoogleAnalyticsMigration($this->getLogger());
        $createdConfigurations = $migration->execute();

        var_dump($createdConfigurations);
    }

    private function getLogger()
    {
        return new Logger(APP_NAME, [
            new \Keboola\ConfigMigrationTool\Logger\InfoHandler(),
            new \Monolog\Handler\StreamHandler('php://stderr', Logger::NOTICE)
        ]);
    }
}
