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
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Monolog\Logger;

class ExGoogleAnalyticsMigrationTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $sapiClient = new Client(['token' => getenv('KBC_TOKEN')]);

        $tables = $sapiClient->listTables('sys.c-ex-google-analytics');
        foreach ($tables as $table) {
            if (false !== strstr($table['id'], 'migrationtest')) {
                $sapiClient->dropTable($table['id']);
            }
        }

        $testTables = [];
        $testTables[] = $this->createOldConfig($sapiClient);
        $testTables[] = $this->createOldConfig($sapiClient);
        $testTables[] = $this->createOldConfig($sapiClient);

        $oldConfigs = [];
        foreach ($testTables as $tableId) {
            $table = $sapiClient->getTable($tableId);
            $attributes = TableHelper::formatAttributes($table['attributes']);

            $oldConfigs[] = [
                'id' => $attributes['id'],
                'accountName' => $attributes['accountName']
            ];
        }

        //@todo
        $migration = new ExGoogleAnalyticsMigration($this->getLogger());
        $createdConfigurations = $migration->execute();

        var_dump($createdConfigurations);
    }

    private function createOldConfig(Client $sapiClient)
    {
        $id = uniqid('migrationtest');

        $tableId = $sapiClient->createTable(
            'sys.c-ex-google-analytics',
            $id,
            new CsvFile(ROOT_PATH . 'tests/data/ex-google-analytics/test.csv')
        );

        $sapiClient->setTableAttribute($tableId, 'id', $id);
        $sapiClient->setTableAttribute($tableId, 'accountName', $id);
        $sapiClient->setTableAttribute($tableId, 'description', 'Migrate this account');
        $sapiClient->setTableAttribute($tableId, 'outputBucket', 'migrationtest');
        $sapiClient->setTableAttribute($tableId, 'googleId', getenv('GOOGLE_ACCOUNT_ID'));
        $sapiClient->setTableAttribute($tableId, 'googleName', 'Some User Name');
        $sapiClient->setTableAttribute($tableId, 'email', getenv('GOOGLE_ACCOUNT_EMAIL'));
        $sapiClient->setTableAttribute($tableId, 'owner', getenv('GOOGLE_ACCOUNT_EMAIL'));
        $sapiClient->setTableAttribute($tableId, 'accessToken', getenv('GOOGLE_ACCESS_TOKEN'));
        $sapiClient->setTableAttribute($tableId, 'refreshToken', getenv('GOOGLE_REFRESH_TOKEN'));

        return $tableId;
    }

    private function getLogger()
    {
        return new Logger(APP_NAME, [
            new \Keboola\ConfigMigrationTool\Logger\InfoHandler(),
            new \Monolog\Handler\StreamHandler('php://stderr', Logger::NOTICE)
        ]);
    }
}
