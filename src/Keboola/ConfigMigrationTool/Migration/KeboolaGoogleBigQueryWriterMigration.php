<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\StorageApi\Options\Components\Configuration;

class KeboolaGoogleBigQueryWriterMigration extends GenericCopyMigration
{
    public function execute() : array
    {
        return parent::doExecute(function (Configuration $configuration) {
            return KeboolaGoogleBigQueryWriterMigration::transform($configuration);
        });
    }

    public static function transform(Configuration $configuration): Configuration
    {
        $configurationData = $configuration->getConfiguration();
        if (isset($configurationData["parameters"]["project"])) {
            unset($configurationData["parameters"]["project"]);
        }
        $configuration->setConfiguration($configurationData);
        return $configuration;
    }
}
