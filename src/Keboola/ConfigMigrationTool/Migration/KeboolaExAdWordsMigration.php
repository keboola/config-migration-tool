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
            if (isset($c['parameters']['customer_id'])) {
                $c['parameters']['customerId'] = $c['parameters']['customer_id'];
                unset($c['parameters']['customer_id']);
            }
            if (isset($c['parameters']['developer_token'])) {
                $c['parameters']['#developerToken'] = $c['parameters']['developer_token'];
                unset($c['parameters']['developer_token']);
            } elseif (isset($c['parameters']['#developer_token'])) {
                $c['parameters']['#developerToken'] = null;
                unset($c['parameters']['#developer_token']);
            } elseif (isset($c['parameters']['#developerToken'])) {
                $c['parameters']['#developerToken'] = null;
            }
            unset($c['parameters']['bucket']);
            $configuration->setConfiguration($c);
            return $configuration;
        };
        return $this->doExecute($migrationHook);
    }
}
