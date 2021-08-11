<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;

class KeboolaExGoogleAnalyticsV4Migration extends GenericCopyMigration
{
    public function execute() : array
    {
        $migrationHook = function (Configuration $configuration) {
            $c = $configuration->getConfiguration();

            unset($c['parameters']['queries']);
            unset($c['parameters']['outputBucket']);

            $configuration->setConfiguration($c);
            return $configuration;
        };
        return $this->doExecute($migrationHook);
    }

    protected function processConfigRows(Configuration $configuration, array $oldConfig): void
    {
        if (!empty($oldConfig['configuration']['parameters']['queries'])) {
            foreach ($oldConfig['configuration']['parameters']['queries'] as $r) {
                $configRow = new ConfigurationRow($configuration);
                $newConfig = [
                    'query' => $r['query'],
                ];
                if (isset($r['endpoint'])) {
                    $newConfig['endpoint'] = $r['endpoint'];
                }
                if (isset($r['outputTable'])) {
                    $newConfig['outputTable'] = $r['outputTable'];
                }
                if (isset($r['antisampling'])) {
                    $newConfig['antisampling'] = $r['antisampling'];
                }
                $configRow
                    ->setName($r['name'])
                    ->setConfiguration(['parameters' => $newConfig]);
                $this->storageApiService->createConfigurationRow($configRow);
            }
        }
    }
}
