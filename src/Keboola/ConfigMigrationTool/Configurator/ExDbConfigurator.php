<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/05/16
 * Time: 12:28
 */
namespace Keboola\ConfigMigrationTool\Configurator;

use Keboola\StorageApi\Options\Components\Configuration;

class ExDbConfigurator
{
    public function configure($attributes, $data)
    {
        $configuration = new Configuration();
        $configuration->setComponentId($this->getComponentId($attributes));
        $configuration->setConfigurationId($attributes['accountId']);
        $configuration->setName($attributes['name']);
        $configuration->setDescription(isset($attributes['desc'])?$attributes['desc']:'');
        $configuration->setConfiguration($this->createConfiguration($attributes, $data));

        return $configuration;
    }

    public function getComponentId($attributes)
    {
        return sprintf(
            'keboola.%s-%s',
            'ex-db',
            $attributes['db.driver']
        );
    }

    private function createConfiguration($attributes, $data)
    {
        $configuration = [
            'parameters' => [
                'db' => [
                    'host' => $attributes['db.host'],
                    'port' => $attributes['db.port'],
                    'database' => $attributes['db.database'],
                    'user' => $attributes['db.user'],
                    'password' => $attributes['db.password']
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
                'incremental' => boolval($row['incremental']),
                'enabled' => boolval($row['enabled'])
            ];
            $id++;
        }

        if ($attributes['db.driver'] == 'mysql') {
            if (isset($attributes['db.ssl.ca'])) {
                $configuration['parameters']['db']['ssl']['ca'] = $attributes['db.ssl.ca'];
            }
            if (isset($attributes['db.ssl.key'])) {
                $configuration['parameters']['db']['ssl']['key'] = $attributes['db.ssl.key'];
            }
            if (isset($attributes['db.ssl.cert'])) {
                $configuration['parameters']['db']['ssl']['cert'] = $attributes['db.ssl.cert'];
            }
        }

        return $configuration;
    }
}
