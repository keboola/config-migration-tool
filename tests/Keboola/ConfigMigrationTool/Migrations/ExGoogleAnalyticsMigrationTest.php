<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 23/05/16
 * Time: 10:36
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Migration\ExGoogleAnalyticsMigration;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;

class ExGoogleAnalyticsMigrationTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $sapiClient = new Client(['token' => getenv('KBC_TOKEN')]);

        $migration = new ExGoogleAnalyticsMigration();

        $oldConfigs = [];

        $tables = $sapiClient->listTables('sys.c-ex-google-analytics');
        foreach ($tables as $table) {
            //@todo
        }

        //@todo
    }

    private function createOldConfig(Client $sapiClient, $driver)
    {
        $tableId = $sapiClient->createTable(
            'sys.c-ex-google-analytics',
            uniqid('migration-test'),
            new CsvFile(ROOT_PATH . 'tests/data/ex-google-analytics/test.csv')
        );

        $sapiClient->setTableAttribute($tableId, 'accountId', 'testConfig' . $driver);
        $sapiClient->setTableAttribute($tableId, 'name', 'testConfig' . $driver);
        $sapiClient->setTableAttribute($tableId, 'desc', 'db-ex migration test account ' . $driver);
        $sapiClient->setTableAttribute($tableId, 'db.host', '127.0.0.1');
        $sapiClient->setTableAttribute($tableId, 'db.port', '3306');
        $sapiClient->setTableAttribute($tableId, 'db.user', 'root');
        $sapiClient->setTableAttribute($tableId, 'db.password', '123456');
        $sapiClient->setTableAttribute($tableId, 'db.database', 'test');
        $sapiClient->setTableAttribute($tableId, 'db.driver', $driver);

        if ($driver == 'mysql') {
            $sapiClient->setTableAttribute($tableId, 'db.ssl.key', 'sslkey');
            $sapiClient->setTableAttribute($tableId, 'db.ssl.cert', 'sslcert');
            $sapiClient->setTableAttribute($tableId, 'db.ssl.ca', 'sslca');
        }

        return $tableId;
    }
}
