<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 06/01/17
 * Time: 13:51
 */

namespace Keboola\ConfigMigrationTool\Test;

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
        $this->init('mysql');
    }

    protected function init($driver)
    {
        $this->sapiService = new StorageApiService();
        $sapiClient = $this->sapiService->getClient();
        $sysBucketId = sprintf('sys.c-wr-db-%s-migration', $driver);
        $componentId = sprintf('wr-db-%s', $driver);

        if ($sapiClient->bucketExists($sysBucketId)) {
            foreach ($sapiClient->listTables($sysBucketId) as $table) {
                $this->sapiService->getClient()->dropTable($table['id']);
            }
            try {
                $sapiClient->dropBucket($sysBucketId);
            } catch (\Exception $e) {
            }
        }
        // create config in SAPI
        try {
            $this->sapiService->deleteConfiguration($componentId, 'migration');
        } catch (\Exception $e) {
        }
        $configuration = new Configuration();
        $configuration->setComponentId($componentId);
        $configuration->setConfigurationId('migration');
        $configuration->setName('migration');
        $configuration->setDescription('Migrate this account');
        $this->sapiService->createConfiguration($configuration);

        // create SYS bucket
        $sapiClient->createBucket($componentId . '-migration', Client::STAGE_SYS, 'Mysql DB Writer');
        $sapiClient->setBucketAttribute($sysBucketId, 'driver', $driver);
        $sapiClient->setBucketAttribute($sysBucketId, 'writer', 'db');
        $sapiClient->setBucketAttribute($sysBucketId, 'writerId', 'migration');
        $sapiClient->setBucketAttribute($sysBucketId, 'db.database', 'wrdb_test');
        $sapiClient->setBucketAttribute($sysBucketId, 'db.driver', $driver);
        $sapiClient->setBucketAttribute($sysBucketId, 'db.host', 'hostname');
        $sapiClient->setBucketAttribute($sysBucketId, 'db.port', '3306');
        $sapiClient->setBucketAttribute($sysBucketId, 'db.user', 'root');
        $sapiClient->setBucketAttribute($sysBucketId, 'db.password', 'password');
    }

    protected function createOldConfigTables($driver)
    {
        $sysBucketId = sprintf('sys.c-wr-db-%s-migration', $driver);
        $id = uniqid('migrationtest');

        $sapiClient = $this->sapiService->getClient();
        $tableId = $sapiClient->createTable(
            $sysBucketId,
            $id,
            new CsvFile(ROOT_PATH . 'tests/data/wr-db-' . $driver . '/migration-test.csv')
        );

        $sapiClient->setTableAttribute($tableId, 'id', $id);
        $sapiClient->setTableAttribute($tableId, 'dbName', 'vouchers');
        $sapiClient->setTableAttribute($tableId, 'name', 'vouchers');
        $sapiClient->setTableAttribute($tableId, 'export', 1);
        $sapiClient->setTableAttribute($tableId, 'tableId', 'in.c-academy.vouchers');

        return $id;
    }

    protected function createOldConfig($driver = 'mysql')
    {
        $testTables = [];
        for ($i=0;$i<5;$i++) {
            $testTables[] = $this->createOldConfigTables($driver);
        }

        return $testTables;
    }
}
