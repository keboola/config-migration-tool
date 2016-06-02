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
        $tables = $sapiService->getConfigurationTables('ex-google-analytics');

        $createdConfigurations = [];
        foreach ($tables as $table) {
            $attributes = TableHelper::formatAttributes($table['attributes']);
            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] != 'success') {
                try {
                    $tableData = $sapiService->exportTable($table['id']);

                    $credentials = $oauthService->createCredentials('keboola.ex-google-analytics-v4');

                    $configuration = $configurator->create($attributes);
                    $sapiService->createConfiguration($configuration);
                    $configuration->setConfiguration($configurator->configure($attributes, $tableData));
                    $sapiService->encryptConfiguration($configuration);

                    $this->logger->info(sprintf(
                        "Configuration '%s' has been migrated",
                        $configuration->getName()
                    ));

                    $orchestratorService->updateOrchestrations('ex-db', $configuration);

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
        // TODO: Implement status() method.
    }
}
