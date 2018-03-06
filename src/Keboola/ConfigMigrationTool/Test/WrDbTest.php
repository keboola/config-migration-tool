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
use PHPUnit\Framework\TestCase;

class WrDbTest extends TestCase
{
    protected $driver = 'mysql';

    /** @var StorageApiService */
    protected $sapiService;

    public function setUp()
    {
        $this->init($this->driver);
    }

    protected function getOldComponentId()
    {
        return 'wr-db-' . $this->driver;
    }

    protected function getNewComponentId()
    {
        return ($this->driver == 'redshift')?'keboola.wr-redshift-v2':'keboola.wr-db-' . $this->driver;
    }

    protected function init($driver)
    {
        $this->sapiService = new StorageApiService();
        $sapiClient = $this->sapiService->getClient();
        $sysBucketId = sprintf('sys.c-wr-db-%s-migration', $driver);
        $componentId = $this->getOldComponentId();

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
        try {
            $this->sapiService->deleteConfiguration($this->getNewComponentId(), 'migration');
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

        if ($this->driver == 'redshift') {
            $sapiClient->setBucketAttribute($sysBucketId, 'db.schema', 'public');
        }
    }

    protected function createOldConfigTables()
    {
        $sysBucketId = sprintf('sys.c-wr-db-%s-migration', $this->driver);
        $id = uniqid('migrationtest');

        $sapiClient = $this->sapiService->getClient();
        $tableId = $sapiClient->createTable(
            $sysBucketId,
            $id,
            new CsvFile(ROOT_PATH . 'tests/data/wr-db-' . $this->driver . '/migration-test.csv')
        );

        $sapiClient->setTableAttribute($tableId, 'id', $id);
        $sapiClient->setTableAttribute($tableId, 'dbName', 'vouchers');
        $sapiClient->setTableAttribute($tableId, 'name', 'vouchers');
        $sapiClient->setTableAttribute($tableId, 'export', 1);
        $sapiClient->setTableAttribute($tableId, 'tableId', 'in.c-academy.vouchers');

        return $id;
    }

    protected function createOldConfig()
    {
        $testTables = [];
        for ($i=0; $i<5; $i++) {
            $testTables[] = $this->createOldConfigTables();
        }

        return $testTables;
    }
}
