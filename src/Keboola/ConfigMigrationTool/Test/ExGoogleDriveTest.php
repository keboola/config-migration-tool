<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Options\Components\Configuration;
use PHPUnit\Framework\TestCase;

class ExGoogleDriveTest extends TestCase
{
    /** @var StorageApiService */
    protected $sapiService;

    public function setUp() : void
    {
        $this->sapiService = new StorageApiService();

        // cleanup
        $tables = $this->sapiService->getClient()->listTables('sys.c-ex-google-drive');
        foreach ($tables as $table) {
            $attributes = TableHelper::formatAttributes($table['attributes']);
            if (false !== strstr($table['id'], 'migrationtest')) {
                $this->sapiService->getClient()->dropTable($table['id']);

                try {
                    $this->sapiService->deleteConfiguration('ex-google-drive', $attributes['id']);
                } catch (\Throwable $e) {
                }
            }
        }
    }

    protected function createOldConfig() : string
    {
        $id = uniqid('migrationtest');

        $sapiClient = $this->sapiService->getClient();
        $tableId = $sapiClient->createTable(
            'sys.c-ex-google-drive',
            $id,
            new CsvFile(ROOT_PATH . '/data/ex-google-drive/migration-test.csv')
        );

        $sapiClient->setTableAttribute($tableId, 'id', $id);
        $sapiClient->setTableAttribute($tableId, 'accountName', $id);
        $sapiClient->setTableAttribute($tableId, 'description', 'Migrate this account');
        //$sapiClient->setTableAttribute($tableId, 'outputBucket', 'migrationtest');
        $sapiClient->setTableAttribute($tableId, 'googleId', getenv('GOOGLE_ACCOUNT_ID'));
        $sapiClient->setTableAttribute($tableId, 'googleName', 'Some User Name');
        $sapiClient->setTableAttribute($tableId, 'email', getenv('GOOGLE_ACCOUNT_EMAIL'));
        $sapiClient->setTableAttribute($tableId, 'owner', getenv('GOOGLE_ACCOUNT_EMAIL'));
        $sapiClient->setTableAttribute($tableId, 'accessToken', getenv('GOOGLE_ACCESS_TOKEN'));
        $sapiClient->setTableAttribute($tableId, 'refreshToken', getenv('GOOGLE_REFRESH_TOKEN'));

        // create config in SAPI
        $configuration = new Configuration();
        $configuration->setComponentId('ex-google-drive');
        $configuration->setConfigurationId($id);
        $configuration->setName($id);
        $configuration->setDescription('Migrate this account');
        $this->sapiService->createConfiguration($configuration);

        return $id;
    }

    protected function createOldConfigs() : array
    {
        $testTables = [];
        for ($i = 0; $i < 5; $i++) {
            $testTables[] = $this->createOldConfig();
        }

        return $testTables;
    }
}
