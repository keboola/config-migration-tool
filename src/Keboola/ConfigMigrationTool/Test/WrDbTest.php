<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 06/01/17
 * Time: 13:51
 */

namespace Keboola\ConfigMigrationTool\Test;


use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Options\Components\Configuration;

class WrDbTest extends \PHPUnit_Framework_TestCase
{
    /** @var StorageApiService */
    protected $sapiService;

    public function setUp()
    {
        $this->sapiService = new StorageApiService();
        $sapiClient = $this->sapiService->getClient();
        $sysBucketId = 'sys.c-wr-db-mysql-migration';

        if ($sapiClient->bucketExists($sysBucketId)) {
            foreach ($sapiClient->listTables('sys.c-wr-db-mysql-migration') as $table) {
                $attributes = TableHelper::formatAttributes($table['attributes']);
                $this->sapiService->getClient()->dropTable($table['id']);
                try {
                    $this->sapiService->deleteConfiguration('wr-db-mysql', $attributes['id']);
                } catch (\Exception $e) {
                }
            }
            $sapiClient->dropBucket($sysBucketId);
        }
        $sapiClient->createBucket('wr-db-mysql-migration', Client::STAGE_SYS, 'Mysql DB Writer');
        $sapiClient->setBucketAttribute($sysBucketId, 'driver', 'mysql');
        $sapiClient->setBucketAttribute($sysBucketId, 'writer', 'db');
        $sapiClient->setBucketAttribute($sysBucketId, 'writerId', 'migration');
        $sapiClient->setBucketAttribute($sysBucketId, 'db.database', 'wrdb_test');
        $sapiClient->setBucketAttribute($sysBucketId, 'db.driver', 'mysql');
        $sapiClient->setBucketAttribute($sysBucketId, 'db.host', 'hostname');
        $sapiClient->setBucketAttribute($sysBucketId, 'db.port', '3306');
        $sapiClient->setBucketAttribute($sysBucketId, 'db.user', 'root');
        $sapiClient->setBucketAttribute($sysBucketId, 'db.password', 'password');
    }

    protected function createOldConfigTables()
    {
        $id = uniqid('migrationtest');

        $sapiClient = $this->sapiService->getClient();
        $tableId = $sapiClient->createTable(
            'sys.c-wr-db-mysql-migration',
            $id,
            new CsvFile(ROOT_PATH . 'tests/data/wr-db/migration-test.csv')
        );

        $sapiClient->setTableAttribute($tableId, 'id', $id);
        $sapiClient->setTableAttribute($tableId, 'dbName', 'vouchers');
        $sapiClient->setTableAttribute($tableId, 'name', 'vouchers');
        $sapiClient->setTableAttribute($tableId, 'export', 1);
        $sapiClient->setTableAttribute($tableId, 'tableId', 'in.c-academy.vouchers');

        // create config in SAPI
        $configuration = new Configuration();
        $configuration->setComponentId('wr-db-mysql');
        $configuration->setConfigurationId($id);
        $configuration->setName($id);
        $configuration->setDescription('Migrate this account');
        $this->sapiService->createConfiguration($configuration);

        return $id;
    }

    protected function createOldConfig()
    {
        $testTables = [];
        for ($i=0;$i<5;$i++) {
            $testTables[] = $this->createOldConfigTables();
        }

        return $testTables;
    }
}
