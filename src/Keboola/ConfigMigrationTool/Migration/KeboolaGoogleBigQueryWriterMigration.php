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
        $configurationData["parameters"]["service_account"] = [
            "#private_key" => "",
            "project_id" => "",
            "token_uri" => "",
            "client_email" => "",
            "client_id" => "",
            "auth_uri" => "",
            "auth_provider_x509_cert_url" => "",
            "private_key_id" => "",
            "client_x509_cert_url" => "",
            "type" => "",
        ];
        $configuration->setConfiguration($configurationData);
        return $configuration;
    }
}
