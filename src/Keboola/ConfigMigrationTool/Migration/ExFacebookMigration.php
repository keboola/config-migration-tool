<?php
/**
 * User: tomaskacur
 * Date: 20/02/17
 * Time: 12:46
 */

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Configurator\ExFacebookConfigurator;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\OAuthService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\StorageApi\ClientException;
use Monolog\Logger;

class ExFacebookMigration implements MigrationInterface
{
    /** @var Logger */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function execute()
    {
        $sapiService = new StorageApiService();
        $oauthService = new OAuthService();
        $configurator = new ExFacebookConfigurator();

        $tables = $sapiService->getConfigurationTables('ex-facebook');
        $accountsTable = current(array_filter($tables, function($t) {return $t["name"] == 'accounts';}));
        $accounts = $sapiService->exportTable($accountsTable['id']);
        var_dump($accounts);
        exit;

        $createdConfigurations = [];

            $attributes = TableHelper::formatAttributes($table['attributes']);
            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] != 'success') {
                foreach ($accounts as $account) {

                    try {
                        // get Account from old GA EX
                        $account = $googleDriveService->getAccount($attributes['id']);
                        $account
                            $oauthService->obtainCredentials('keboola.ex-facebook', $account);

                        // get old Configuration from SAPI
                        $oldSapiConfig = $sapiService->getConfiguration('ex-google-drive', $account['id']);
                        $account['accountNamePretty'] = $oldSapiConfig['name'];

                        // create new Configuration in SAPI
                        $configuration = $configurator->create($account);
                        $sapiService->createConfiguration($configuration);

                        // create and store encrypted parameters
                        $cfg = $configurator->configure($account);
                        $configuration->setConfiguration($cfg);
                        $sapiService->encryptConfiguration($configuration);

                        $this->logger->info(sprintf(
                            "Configuration '%s' has been migrated",
                            $configuration->getName()
                        ));

                        $createdConfigurations[] = $configuration;
                    } catch (ClientException $e) {
                        $sapiService->getClient()->setTableAttribute($table['id'], 'migrationStatus', 'error: ' . $e->getMessage());
                        throw new UserException("Error occured during migration: " . $e->getMessage(), 500, $e, [
                            'tableId' => $table['id']
                        ]);
                    } catch (\Exception $e) {
                        $sapiService->getClient()->setTableAttribute($table['id'], 'migrationStatus', 'error: ' . $e->getMessage());
                        throw new ApplicationException("Error occured during migration: " . $e->getMessage(), 500, $e, [
                            'tableId' => $table['id']
                        ]);
                    }
                }
            }

            $sapiService->getClient()->setTableAttribute($accountsTable['id'], 'migrationStatus', 'success');
            return $createdConfigurations;
    }

    public function status()
    {
        $sapiService = new StorageApiService();


        $tables = $sapiService->getConfigurationTables('ex-google-drive');
        return [
            'configurations' => array_map(
                function ($item) {
                    $attributes = TableHelper::formatAttributes($item['attributes']);
                    return [
                        'configId' => $attributes['id'],
                        'configName' => $attributes['accountName'],
                        'componentId' => 'keboola.ex-google-drive',
                        'tableId' => $item['id'],
                        'status' => isset($attributes['migrationStatus'])?$attributes['migrationStatus']:'n/a'
                    ];
                },
                $tables
            ),
            'orchestrations' => []
        ];
    }
}
