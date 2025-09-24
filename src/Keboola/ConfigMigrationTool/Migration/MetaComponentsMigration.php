<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\StorageApi\Options\Components\Configuration;

class MetaComponentsMigration extends GenericCopyMigration
{
    public function execute(): array
    {
        return $this->doExecute([$this, 'addCustomBucketParameter']);
    }

    /**
     * Přidá custom-bucket parametr do konfigurace s hodnotou c-componentid-configurationid
     * Všechny _ a . jsou nahrazeny za -
     *
     * @param Configuration $configuration
     * @return Configuration
     */
    public function addCustomBucketParameter(Configuration $configuration): Configuration
    {
        $config = $configuration->getConfiguration();
        
        // Přidáme custom-bucket parametr do parameters sekce
        if (!isset($config['parameters'])) {
            $config['parameters'] = [];
        }
        
        $componentId = str_replace(['_', '.'], '-', $this->destinationComponentId);
        $configurationId = $configuration->getConfigurationId();
        $config['parameters']['custom-bucket'] = 'in.c-' . $componentId . '-' . $configurationId;
        
        $configuration->setConfiguration($config);
        
        return $configuration;
    }
}
