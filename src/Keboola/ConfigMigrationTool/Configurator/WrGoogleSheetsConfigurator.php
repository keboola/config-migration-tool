<?php
/**
 * Author: miro@keboola.com
 * Date: 23/05/2017
 */
namespace Keboola\ConfigMigrationTool\Configurator;

use Keboola\StorageApi\Options\Components\Configuration;

class WrGoogleSheetsConfigurator
{
    public function create($account)
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

    public function getComponentId()
    {
        return 'keboola.wr-google-sheets';
    }

    public function getTableAttributeValue($table, $name)
    {
        foreach ($table['attributes'] as $attribute) {
            if ($attribute['name'] == $name) {
                return $attribute['value'];
            }
        }

        return null;
    }

    public function configure($account)
    {
        $configuration = [
            'authorization' => [
                'oauth_api' => ['id' => $account['id']]
            ],
            'parameters' => ['tables' => $this->configureTables($account['items'])],
            'storage' => ['input' => ['tables' => $this->configureInputMapping($account['items'])]]
        ];

        return $configuration;
    }

    protected function configureTables($items)
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
                'tableId' => $item['tableId']
            ];
        }

        return $tables;
    }

    protected function configureInputMapping($items)
    {
        return array_map(
            function ($item) {
                return [
                    'source' => $item['tableId'],
                    'destination' => $item['tableId'] . '.csv'
                ];
            },
            $items
        );
    }
}
