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
        $encryptDeveloperToken = function (Configuration $configuration) {
            $c = $configuration->getConfiguration();
            if (isset($c['#developerToken'])) {
                $c['#developerToken'] = $c['developerToken'];
                unset($c['developerToken']);
                $configuration->setConfiguration($c);
            }
            return $configuration;
        };
        return $this->doExecute($encryptDeveloperToken);
    }
}
