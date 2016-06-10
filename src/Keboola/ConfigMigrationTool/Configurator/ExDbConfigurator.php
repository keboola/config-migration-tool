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
    /**
     * @param $attributes
     * @return Configuration
     */
    public function create($attributes, $name)
    {
        $configuration = new Configuration();
        $configuration->setComponentId($this->getComponentId($attributes));
        $configuration->setConfigurationId($attributes['accountId']);
        $configuration->setName($name);
        $configuration->setDescription(isset($attributes['desc'])?$attributes['desc']:'');

        return $configuration;
    }

    /**
     * @param $attributes
     * @return string
     */
    public function getComponentId($attributes)
    {
        return sprintf(
            'keboola.%s-%s',
            'ex-db',
            isset($attributes['db.driver'])?$attributes['db.driver']:'mysql'
        );
    }

    /**
     * @param $credentials
     * @param $queries
     * @return array
     */
    public function configure($credentials, $queries)
    {
        // configuration can be empty
        if (!isset($credentials['host'])) {
            return [];
        }
        $configuration = [
            'parameters' => [
                'db' => [
                    'host' => $credentials['host'],
                    'port' => $credentials['port'],
                    'database' => $credentials['database'],
                    'user' => $credentials['user'],
                    '#password' => $credentials['password']
                ],
            ]
        ];

        $id = 0;
        foreach ($queries as $row) {
            $configuration['parameters']['tables'][] = [
                'id' => $id,
                'name' => empty($row['name'])?'untitled':$row['name'],
                'query' => $row['query'],
                'outputTable' => $row['outputTable'],
                'incremental' => boolval($row['incremental']),
                'primaryKey' => explode(',', $row['primaryKey']),
                'enabled' => boolval($row['enabled'])
            ];
            $id++;
        }

        if ($credentials['driver'] == 'mysql') {
            if (isset($credentials['ssl'])) {
                $configuration['parameters']['db']['ssl'] = $credentials['ssl'];
                $configuration['parameters']['db']['ssl']['enabled'] = true;
            }
        }

        return $configuration;
    }
}
