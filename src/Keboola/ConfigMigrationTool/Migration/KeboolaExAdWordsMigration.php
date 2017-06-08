<?php
/**
 * @package config-migration-tool
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\StorageApi\Options\Components\Configuration;

class KeboolaExAdWordsMigration extends GenericCopyMigration
{
    public function execute()
    {
        $migrationHook = function (Configuration $configuration) {
            $c = $configuration->getConfiguration();
            if (isset($c['customer_id'])) {
                $c['customerId'] = $c['customer_id'];
                unset($c['customer_id']);
            }
            if (isset($c['developer_token'])) {
                $c['#developerToken'] = $c['developer_token'];
                unset($c['developer_token']);
            } elseif (isset($c['#developer_token'])) {
                $c['#developerToken'] = null;
                unset($c['#developer_token']);
            } elseif (isset($c['#developerToken'])) {
                $c['#developerToken'] = null;
            }
            unset($c['bucket']);
            $configuration->setConfiguration($c);
            return $configuration;
        };
        return $this->doExecute($migrationHook);
    }
}
