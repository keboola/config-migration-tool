<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\StorageApi\Options\Components\Configuration;

class KeboolaExAdWordsMigration extends GenericCopyMigration
{
    public function execute() : array
    {
        $migrationHook = function (Configuration $configuration) {
            $c = $configuration->getConfiguration();
            if (isset($c['parameters']['customer_id'])) {
                $c['parameters']['customerId'] = $c['parameters']['customer_id'];
                unset($c['parameters']['customer_id']);
            }
            unset($c['parameters']['#developerToken']);
            unset($c['parameters']['developer_token']);
            unset($c['parameters']['#developer_token']);

            $configuration->setConfiguration($c);
            return $configuration;
        };
        return $this->doExecute($migrationHook);
    }
}
