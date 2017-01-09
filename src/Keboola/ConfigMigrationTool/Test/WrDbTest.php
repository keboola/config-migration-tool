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
        $sysBucketId = 'sys.c-wr-db-mysql-migration';

        if (!$this->sapiService->getClient()->bucketExists($sysBucketId)) {
            $this->sapiService->getClient()->createBucket('wr-db-mysql-migration', Client::STAGE_SYS, 'Mysql DB Writer');
            $this->sapiService->getClient()->setBucketAttribute($sysBucketId, 'driver', 'mysql');
            $this->sapiService->getClient()->setBucketAttribute($sysBucketId, 'writer', 'db');
            $this->sapiService->getClient()->setBucketAttribute($sysBucketId, 'writerId', 'migration');
        }

        // cleanup
        $tables = $this->sapiService->getClient()->listTables('sys.c-wr-db-mysql-migration');
        foreach ($tables as $table) {
            $attributes = TableHelper::formatAttributes($table['attributes']);
            if (false !== strstr($table['id'], 'migrationtest')) {
                $this->sapiService->getClient()->dropTable($table['id']);
                try {
                    $this->sapiService->deleteConfiguration('wr-db-mysql', $attributes['id']);
                } catch (\Exception $e) {
                }
            }
        }
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
