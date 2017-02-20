<?php
/**
 *
 * User: tomaskacur
 * Date: 20/02/17
 * Time: 12:47
 */

namespace Keboola\ConfigMigrationTool\Configurator;

use Keboola\StorageApi\Options\Components\Configuration;

class ExFacebookConfigurator
{
    public function create($account)
    {
        $configuration = new Configuration();
        $configuration->setComponentId($this->getComponentId());
        $configuration->setConfigurationId($account['id']);
        $configuration->setName($account['accountNamePretty']);
        $configuration->setDescription($account['description']);

        return $configuration;
    }

    public function getComponentId()
    {
        return 'keboola.ex-facebook';
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
            ]
        ];
        return $configuration;
    }

}
