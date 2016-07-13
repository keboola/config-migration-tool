<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 01/07/16
 * Time: 10:18
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;

class ExGoogleAnalyticsTest extends \PHPUnit_Framework_TestCase
{
    /** @var Client */
    protected $sapiClient;

    public function setUp()
    {
        $this->sapiClient = new Client(['token' => getenv('KBC_TOKEN')]);

        // cleanup
        $tables = $this->sapiClient->listTables('sys.c-ex-google-analytics');
        foreach ($tables as $table) {
            if (false !== strstr($table['id'], 'migrationtest')) {
                $this->sapiClient->dropTable($table['id']);
            }
        }
    }

    protected function createOldConfig()
    {
        $id = uniqid('migrationtest');

        $tableId = $this->sapiClient->createTable(
            'sys.c-ex-google-analytics',
            $id,
            new CsvFile(ROOT_PATH . 'tests/data/ex-google-analytics/migration-test.csv')
        );

        $this->sapiClient->setTableAttribute($tableId, 'id', $id);
        $this->sapiClient->setTableAttribute($tableId, 'accountName', $id);
        $this->sapiClient->setTableAttribute($tableId, 'description', 'Migrate this account');
        $this->sapiClient->setTableAttribute($tableId, 'outputBucket', 'migrationtest');
        $this->sapiClient->setTableAttribute($tableId, 'googleId', getenv('GOOGLE_ACCOUNT_ID'));
        $this->sapiClient->setTableAttribute($tableId, 'googleName', 'Some User Name');
        $this->sapiClient->setTableAttribute($tableId, 'email', getenv('GOOGLE_ACCOUNT_EMAIL'));
        $this->sapiClient->setTableAttribute($tableId, 'owner', getenv('GOOGLE_ACCOUNT_EMAIL'));
        $this->sapiClient->setTableAttribute($tableId, 'accessToken', getenv('GOOGLE_ACCESS_TOKEN'));
        $this->sapiClient->setTableAttribute($tableId, 'refreshToken', getenv('GOOGLE_REFRESH_TOKEN'));

        $queries = $this->createQueriesConfig();
        if (rand(0,9) >= 5) {
            $queriesArr = json_decode($queries, true);
            $queriesArr['Users']['profile'] = 69127714;
            $queries = json_encode($queriesArr);
        }

        $this->sapiClient->setTableAttribute($tableId, 'configuration', $queries);

        return $id;
    }

    protected function createOldConfigs()
    {
        $testTables = [];
        for ($i=0;$i<5;$i++) {
            $testTables[] = $this->createOldConfig();
        }

        return $testTables;
    }

    protected function createQueriesConfig()
    {
        return '{"Users":{"metrics":["users"],"dimensions":["sourceMedium"]},"OrganicTraffic":{"name":"OrganicTraffic","metrics":["users","sessions"],"dimensions":["date","segment"],"filters":["ga:timeOnPage>10"],"segment":"gaid::-5"}}';
    }
}
