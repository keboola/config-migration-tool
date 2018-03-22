<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Configurator;

use Keboola\StorageApi\Options\Components\Configuration;

class WrGoogleSheetsConfigurator
{
    public function create(array $account) : Configuration
    {
        $configuration = new Configuration();
        $configuration->setComponentId($this->getComponentId());
        $configuration->setConfigurationId($account['id']);
        $configuration->setName(
            empty($account['accountNamePretty'])
                ? $account['name']
                : $account['accountNamePretty']
        );
        $configuration->setDescription(empty($account['description']) ? '' : $account['description']);

        return $configuration;
    }

    public function getComponentId() : string
    {
        return 'keboola.wr-google-sheets';
    }

    public function getTableAttributeValue(array $table, string $name) : ?string
    {
        foreach ($table['attributes'] as $attribute) {
            if ($attribute['name'] == $name) {
                return $attribute['value'];
            }
        }

        return null;
    }

    public function configure(array $account) : array
    {
        $configuration = [
            'authorization' => [
                'oauth_api' => ['id' => $account['id']],
            ],
            'parameters' => ['tables' => $this->configureTables($account['items'])],
            'storage' => ['input' => ['tables' => $this->configureInputMapping($account['items'])]],
        ];

        return $configuration;
    }

    protected function configureTables(array $items) : array
    {
        $tables = [];
        $cnt = 0;
        foreach ($items as $item) {
            $tables[] = [
                'id' => $cnt++,
                'fileId' => $item['googleId'],
                'title' => $item['title'],
                'sheetId' => $item['sheetId'],
                'sheetTitle' => $item['sheetTitle'],
                'enabled' => true,
                'folder' => $item['folder'],
                'action' => $item['operation'],
                'tableId' => $item['tableId'],
            ];
        }

        return $tables;
    }

    protected function configureInputMapping(array $items) : array
    {
        return array_map(
            function ($item) {
                return [
                    'source' => $item['tableId'],
                    'destination' => $item['tableId'] . '.csv',
                ];
            },
            $items
        );
    }
}
