<?php
/**
 * Author: miro@keboola.com
 * Date: 23/05/2017
 */
namespace Keboola\ConfigMigrationTool\Configurator;

use Keboola\StorageApi\Options\Components\Configuration;

class WrGoogleDriveConfigurator
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
        return 'keboola.wr-google-drive';
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
        return array_map(function ($item, $key) {
            return [
                'id' => $key,
                'fileId' => $item['googleId'],
                'title' => $item['title'],
                'enabled' => true,
                'folder' => $item['folder'],
                'action' => $item['operation'],
                'tableId' => $item['tableId'],
                'convert' => false
            ];
        }, $items, array_keys($items));
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
