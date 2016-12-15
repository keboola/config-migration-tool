<?php
/**
 * @copy Keboola 2016
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Options\Components\Configuration;

class ExGoodDataTest extends \PHPUnit_Framework_TestCase
{
    /** @var StorageApiService */
    protected $sapiService;

    public function setUp()
    {
        $this->sapiService = new StorageApiService();

        // cleanup
        $tables = $this->sapiService->getClient()->listTables('sys.c-ex-gooddata');
        foreach ($tables as $table) {
            $attributes = TableHelper::formatAttributes($table['attributes']);
            if (false !== strstr($table['id'], 'migrationtest')) {
                $this->sapiService->getClient()->dropTable($table['id']);

                try {
                    $this->sapiService->deleteConfiguration('ex-gooddata', $attributes['id']);
                } catch (\Exception $e) {
                }
            }
        }

        foreach ($this->sapiService->getConfigurations('ex-gooddata') as $configuration) {
            if (substr($configuration['id'], 0, 13) == 'migrationtest') {
                $this->sapiService->deleteConfiguration('ex-gooddata', $configuration['id']);
            }
        }
    }

    protected function createOldConfig()
    {
        $id = uniqid('migrationtest');

        $sapiClient = $this->sapiService->getClient();
        $tableId = $sapiClient->createTable(
            'sys.c-ex-gooddata',
            $id,
            new CsvFile(ROOT_PATH . 'tests/data/ex-gooddata/test.csv')
        );

        $sapiClient->setTableAttribute($tableId, 'id', $id);
        $sapiClient->setTableAttribute($tableId, 'name', $id);
        $sapiClient->setTableAttribute($tableId, 'description', 'Migrate this account');

        // create config in SAPI
        $configuration = new Configuration();
        $configuration->setComponentId('ex-gooddata');
        $configuration->setConfigurationId($id);
        $configuration->setName($id);
        $configuration->setDescription('Migrate this account');
        $this->sapiService->createConfiguration($configuration);

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
}
