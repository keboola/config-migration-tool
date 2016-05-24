<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 23/05/16
 * Time: 12:47
 */

namespace Keboola\ConfigMigrationTool\Configurator;

use Keboola\StorageApi\Options\Components\Configuration;

class ExGaConfigurator
{
    public function configure($table, $data)
    {
        $configuration = new Configuration();
        $configuration->setComponentId($this->getComponentId());
        $configuration->setName($this->getTableAttributeValue($table, 'name'));
        $configuration->setDescription($this->getTableAttributeValue($table, 'desc'));
        $configuration->setConfiguration($this->createConfiguration($table, $data));

        return $configuration;
    }

    public function getComponentId()
    {
        return 'keboola.ex-google-analytics-v4';
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

    private function createConfiguration($table, $data)
    {
        $configuration = [
            'parameters' => [
                'outputBucket' => 'in.c-ex-google-analytics-' . $this->getTableAttributeValue($table, 'id'),
                'profiles' => [
                    [
                        'id' => $this->getTableAttributeValue($table, 'db.host'),
                    ]
                ]
            ]
        ];

        $id = 0;
        foreach ($data as $row) {
            $configuration['parameters']['queries'][] = [
                'id' => $id,
                'name' => $row['name'],
                'query' => [

                ],
                'outputTable' => $row['outputTable'],
                'incremental' => $row['incremental'],
                'enabled' => $row['enabled']
            ];
            $id++;
        }

        return $configuration;
    }
}
