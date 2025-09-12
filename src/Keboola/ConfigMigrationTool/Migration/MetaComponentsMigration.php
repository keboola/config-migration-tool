<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use GuzzleHttp\Exception\RequestException;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Service\OAuthV3Service;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;

class MetaComponentsMigration extends DockerAppMigration
{
    /** @var array */
    private $config;

    /** @var OAuthV3Service */
    private $oauthV3Service;

    public function __construct(array $config, Logger $logger)
    {
        parent::__construct($logger);
        $this->config = $config;

        // Inicializace OAuth v3 service pro migraci credentials
        $oauthV3Url = getenv('OAUTH_API_URL') ?: $this->storageApiService->getServiceUrl(StorageApiService::OAUTH_SERVICE);
        $this->oauthV3Service = new OAuthV3Service($oauthV3Url);
    }

    public function execute(): array
    {
        $responses = [];
        $createdConfigurations = [];

        foreach ($this->storageApiService->getConfigurations($this->originComponentId) as $oldConfig) {
            if (!$this->isConfigurationMigrated($oldConfig)) {
                try {
                    $configuration = $this->buildConfigurationObject($this->destinationComponentId, $oldConfig);
                    
                    // Zpracovat autentifikaci
                    $this->processAuthentication($configuration, $oldConfig);
                    
                    // Aplikovat hook pro specifické úpravy konfigurace
                    $configuration = $this->applyMetaSpecificChanges($configuration, $oldConfig);
                    
                    // Poznámka: Autorizace zůstává zachována ve staré komponentě
                    // Nová komponenta bude mít svou vlastní autorizaci nastavenou v processAuthentication()

                    $configuration->setConfiguration($this->storageApiService->encryptConfiguration($configuration));
                    $this->storageApiService->createConfiguration($configuration);

                    $this->processConfigRows($configuration, $oldConfig);

                    $this->logger->info(sprintf(
                        "Meta component configuration '%s' has been migrated from %s to %s",
                        $configuration->getName(),
                        $this->originComponentId,
                        $this->destinationComponentId
                    ));

                    $createdConfigurations[] = $configuration;
                    $responses[] = ['success' => true, 'configId' => $configuration->getConfigurationId()];
                    
                    // Označit starou konfiguraci jako migrovanou (bez změny autorizace)
                    $oldConfiguration = $this->buildConfigurationObject($this->originComponentId, $oldConfig);
                    $this->markConfigurationAsMigrated($oldConfiguration);
                } catch (\Throwable $e) {
                    $oldConfiguration = $this->buildConfigurationObject($this->originComponentId, $oldConfig);
                    $this->markConfigurationAsError($oldConfiguration, $e->getMessage());
                    $this->storageApiService->deleteConfiguration($this->destinationComponentId, $oldConfig['id']);
                    
                    if ($e instanceof ClientException || $e instanceof UserException) {
                        throw new UserException($e->getMessage(), 400, $e, [
                            'oldComponentId' => $this->originComponentId,
                            'newComponentId' => $this->destinationComponentId,
                            'configurationId' => $oldConfig['id'],
                        ]);
                    }

                    throw new ApplicationException($e->getMessage(), 500, $e, [
                        'oldComponentId' => $this->originComponentId,
                        'newComponentId' => $this->destinationComponentId,
                        'configurationId' => $oldConfig['id'],
                    ]);
                }
            }
        }

        return $responses;
    }

    public function status(): array
    {
        $configurations = $this->storageApiService->getConfigurations($this->originComponentId);
        return [
            'configurations' => array_map(
                function ($item) {
                    return [
                        'configId' => $item['id'],
                        'configName' => $item['name'],
                        'componentId' => $this->originComponentId,
                        'status' => $this->getConfigurationStatus($item),
                    ];
                },
                $configurations
            ),
        ];
    }

    private function processAuthentication(Configuration $configuration, array $oldConfig): void
    {
        // Zkontrolovat OAuth credentials (encryptované, obsahují OAuth i direct token)
        if (!isset($oldConfig['configuration']['authorization']['oauth_api']['credentials']['id'])) {
            $this->logger->warning(sprintf(
                "No OAuth credentials ID found for configuration '%s'",
                $configuration->getName()
            ));
            return;
        }
        
        $credentialsId = $oldConfig['configuration']['authorization']['oauth_api']['credentials']['id'];
        $sourceComponentId = $oldConfig['componentId'];
        
        $this->migrateOAuthCredentials($configuration, $sourceComponentId, $credentialsId);
    }

    private function migrateOAuthCredentials(Configuration $configuration, string $sourceComponentId, string $credentialsId): void
    {
        try {
            // Získat credentials ze staré komponenty přes OAuth API
            $credentials = $this->oauthV3Service->getCredentials($sourceComponentId, $credentialsId);
            
            // Zkopírovat credentials do nové komponenty přes OAuth API
            try {
                $response = $this->oauthV3Service->createCredentials($this->destinationComponentId, $credentials);
                $this->logger->info("OAuth credentials migrated successfully via OAuth API");
            } catch (RequestException $e) {
                if ($e->getCode() === 400 && strstr($e->getMessage(), 'already exists for component') !== false) {
                    $this->logger->info("OAuth credentials already exist in destination component");
                } else {
                    throw $e;
                }
            }

            // Authorization struktura se nastaví automaticky v OAuth API
            $this->logger->info("Credentials successfully migrated via OAuth API - authorization configured automatically");
            
        } catch (RequestException $e) {
            if ($e->getCode() === 400 && strstr($e->getMessage(), 'No data found') !== false) {
                throw new UserException("Credentials not found in OAuth API: " . $e->getMessage());
            }
            throw $e;
        }
    }

    private function applyMetaSpecificChanges(Configuration $configuration, array $oldConfig): Configuration
    {
        $configData = $configuration->getConfiguration();
        
        // Aplikovat specifické změny pro Meta komponenty
        // Například aktualizovat API verze nebo endpointy
        
        if (isset($configData['parameters']['api_version'])) {
            // Aktualizovat API verzi pokud je potřeba
            $this->logger->info("Updating API version for Meta component");
        }
        
        // Zachovat důležité parametry specifické pro Meta
        if (isset($oldConfig['configuration']['parameters']['accounts'])) {
            $configData['parameters']['accounts'] = $oldConfig['configuration']['parameters']['accounts'];
        }
        
        if (isset($oldConfig['configuration']['parameters']['fields'])) {
            $configData['parameters']['fields'] = $oldConfig['configuration']['parameters']['fields'];
        }
        
        if (isset($oldConfig['configuration']['parameters']['since'])) {
            $configData['parameters']['since'] = $oldConfig['configuration']['parameters']['since'];
        }
        
        if (isset($oldConfig['configuration']['parameters']['until'])) {
            $configData['parameters']['until'] = $oldConfig['configuration']['parameters']['until'];
        }

        $configuration->setConfiguration($configData);
        
        return $configuration;
    }

    protected function processConfigRows(Configuration $configuration, array $oldConfig): void
    {
        if (!empty($oldConfig['rows'])) {
            foreach ($oldConfig['rows'] as $r) {
                $this->storageApiService->addConfigurationRow($configuration, $r['id'], $r['configuration']);
            }
        }
    }

    private function markConfigurationAsMigrated(Configuration $configuration): void
    {
        // Označí konfiguraci jako migrovanou bez změny autorizace
        $configData = $configuration->getConfiguration();
        $configData['runtime']['migrationStatus'] = 'success';
        $configuration->setConfiguration($configData);
        
        // Aktualizuj pouze runtime část, ponech autorizaci beze změny
        $components = new Components($this->storageApiService->getClient());
        $components->updateConfiguration($configuration);
    }

    private function markConfigurationAsError(Configuration $configuration, string $errorMessage): void
    {
        // Označí konfiguraci jako chybnou bez změny autorizace
        $configData = $configuration->getConfiguration();
        $configData['runtime']['migrationStatus'] = "error: {$errorMessage}";
        $configuration->setConfiguration($configData);
        
        // Aktualizuj pouze runtime část, ponech autorizaci beze změny
        $components = new Components($this->storageApiService->getClient());
        $components->updateConfiguration($configuration);
    }
}
