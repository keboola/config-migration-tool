<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 23/05/16
 * Time: 12:46
 */

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Configurator\ExGoogleAnalyticsConfigurator;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\ExGoogleAnalyticsService;
use Keboola\ConfigMigrationTool\Service\OAuthService;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\StorageApi\ClientException;
use Monolog\Logger;

class ExGoogleAnalyticsMigration implements MigrationInterface
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
        $orchestratorService = new OrchestratorService($this->logger);
        $oauthService = new OAuthService();
        $configurator = new ExGoogleAnalyticsConfigurator();
        $googleAnalyticsService = new ExGoogleAnalyticsService($this->logger);

        $tables = $sapiService->getConfigurationTables('ex-google-analytics');

        $createdConfigurations = [];
        foreach ($tables as $table) {
            $attributes = TableHelper::formatAttributes($table['attributes']);
            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] != 'success') {

                if (!isset($attributes['email']) || !isset($attributes['accessToken']) || !isset($attributes['refreshToken'])) {
                    $sapiService->getClient()->setTableAttribute($table['id'], 'migrationStatus', 'skipped: not authorized');
                    continue;
                }

                try {
                    // get Account from old GA EX
                    $account = $googleAnalyticsService->getAccount($attributes['id']);
                    $oauthService->obtainCredentials('keboola.ex-google-analytics-v4', $account);

                    // get old Configuration from SAPI
                    $oldSapiConfig = $sapiService->getConfiguration('ex-google-analytics', $account['id']);
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

                    // update orchestrations
                    $orchestratorService->updateOrchestrations('ex-google-analytics', $configuration);

                    $this->logger->info(sprintf(
                        "Orchestration task for configuration '%s' has been updated",
                        $configuration->getName()
                    ));

                    $createdConfigurations[] = $configuration;
                    $sapiService->getClient()->setTableAttribute($table['id'], 'migrationStatus', 'success');
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

        return $createdConfigurations;
    }

    public function status()
    {
        $sapiService = new StorageApiService();
        $orchestratorService = new OrchestratorService($this->logger);

        $tables = $sapiService->getConfigurationTables('ex-google-analytics');
        return [
            'configurations' => array_map(
                function ($item) {
                    $attributes = TableHelper::formatAttributes($item['attributes']);
                    return [
                        'configId' => $attributes['id'],
                        'configName' => $attributes['accountName'],
                        'componentId' => 'keboola.ex-google-analytics-v4',
                        'tableId' => $item['id'],
                        'status' => isset($attributes['migrationStatus'])?$attributes['migrationStatus']:'n/a'
                    ];
                },
                $tables
            ),
            'orchestrations' => $orchestratorService->getOrchestrations('ex-google-analytics', 'keboola.ex-google-analytics-v4')
        ];
    }
}
