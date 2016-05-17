<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/05/16
 * Time: 12:28
 */
namespace Keboola\ConfigMigrationTool\Configurations;

use Keboola\StorageApi\Options\Components\Configuration;

class ExDbConfiguration
{
    public function configure($table, $data)
    {
        $configuration = new Configuration();
        $configuration->setComponentId($this->getComponentId($table));
        $configuration->setName($this->getTableAttributeValue($table, 'name'));
        $configuration->setDescription($this->getTableAttributeValue($table, 'desc'));
        $configuration->setConfiguration($this->createConfiguration($table, $data));

        return $configuration;
    }

    public function getComponentId($table)
    {
        return sprintf(
            'keboola.%s-%s',
            'ex-db',
            $this->getTableAttributeValue($table, 'db.driver')
        );
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
                'db' => [
                    'host' => $this->getTableAttributeValue($table, 'db.host'),
                    'port' => $this->getTableAttributeValue($table, 'db.port'),
                    'database' => $this->getTableAttributeValue($table, 'db.database'),
                    'user' => $this->getTableAttributeValue($table, 'db.user'),
                    'password' => $this->getTableAttributeValue($table, 'db.password'),
                ],
            ]
        ];

        $id = 0;
        foreach ($data as $row) {
            $configuration['parameters']['tables'][] = [
                'id' => $id,
                'name' => $row['name'],
                'query' => $row['query'],
                'outputTable' => $row['outputTable'],
                'incremental' => $row['incremental'],
                'enabled' => $row['enabled']
            ];
            $id++;
        }

        return $configuration;
    }
}
