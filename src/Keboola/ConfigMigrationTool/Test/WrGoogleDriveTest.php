<?php
/**
 * Author: miro@keboola.com
 * Date: 13/06/2017
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Options\Components\Configuration;

class WrGoogleDriveTest extends \PHPUnit_Framework_TestCase
{
    /** @var StorageApiService */
    protected $sapiService;

    public function setUp()
    {
        $this->sapiService = new StorageApiService();

        // cleanup
        $tables = $this->sapiService->getClient()->listTables('sys.c-wr-google-drive');
        foreach ($tables as $table) {
            $attributes = TableHelper::formatAttributes($table['attributes']);
            if (false !== strstr($table['id'], 'migrationtest')) {
                $this->sapiService->getClient()->dropTable($table['id']);
                try {
                    $this->sapiService->deleteConfiguration('wr-google-drive', $attributes['id']);
                } catch (\Exception $e) {
                }
            }
        }
    }

    protected function createOldConfig()
    {
        $id = uniqid('migrationtest');

        $sapiClient = $this->sapiService->getClient();
        $tableId = $sapiClient->createTable(
            'sys.c-wr-google-drive',
            $id,
            new CsvFile(ROOT_PATH . 'tests/data/wr-google-drive/migration-test.csv')
        );

        $sapiClient->setTableAttribute($tableId, 'id', $id);
        $sapiClient->setTableAttribute($tableId, 'name', $id);
        $sapiClient->setTableAttribute($tableId, 'description', 'Migrate this account');
        $sapiClient->setTableAttribute($tableId, 'googleId', getenv('GOOGLE_ACCOUNT_ID'));
        $sapiClient->setTableAttribute($tableId, 'googleName', 'Some User Name');
        $sapiClient->setTableAttribute($tableId, 'email', getenv('GOOGLE_ACCOUNT_EMAIL'));
        $sapiClient->setTableAttribute($tableId, 'accessToken', getenv('GOOGLE_ACCESS_TOKEN'));
        $sapiClient->setTableAttribute($tableId, 'refreshToken', getenv('GOOGLE_REFRESH_TOKEN'));

        // create config in SAPI
        $configuration = new Configuration();
        $configuration->setComponentId('wr-google-drive');
        $configuration->setConfigurationId($id);
        $configuration->setName($id);
        $configuration->setDescription('Migrate this account');
        $this->sapiService->createConfiguration($configuration);

        return $id;
    }

    protected function createOldConfigs()
    {
        $testTables = [];
        for ($i=0; $i<5; $i++) {
            $testTables[] = $this->createOldConfig();
        }

        return $testTables;
    }
}
