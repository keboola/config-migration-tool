<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 09/01/17
 * Time: 16:06
 */

namespace Keboola\ConfigMigrationTool\Configurator;

use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\StorageApi\Options\Components\Configuration;

class WrDbConfigurator
{
    protected $driver;

    public function __construct($driver = 'mysql')
    {
        $this->driver = $driver;
    }


    /**
     * @param $attributes
     * @param $prettyName
     * @return Configuration
     */
    public function create($attributes, $prettyName)
    {
        $configuration = new Configuration();
        $configuration->setComponentId($this->getComponentId());
        $configuration->setConfigurationId($attributes['writerId']);
        $configuration->setName($prettyName);
        $configuration->setDescription(isset($attributes['description'])?$attributes['description']:'');

        return $configuration;
    }

    /**
     * @return string
     */
    public function getComponentId()
    {
        return ($this->driver == 'redshift')?'keboola.wr-redshift-v2':'keboola.wr-db-' . $this->driver;
    }

    /**
     * @param $credentials
     * @param $tables
     * @return array
     * @throws ApplicationException
     */
    public function configure($credentials, $tables)
    {
        // configuration can be empty
        if (!isset($credentials['host'])) {
            return [];
        }

        if ($credentials['driver'] !== $this->driver) {
            throw new ApplicationException(sprintf(
                "Driver mismatch: expected driver %s, but driver in credentials is %s",
                $this->driver,
                $credentials['driver']
            ));
        }

        $configuration = [
            'parameters' => [
                'db' => [
                    'host' => $credentials['host'],
                    'port' => $credentials['port'],
                    'database' => $credentials['database'],
                    'user' => $credentials['user'],
                    '#password' => $credentials['password'],
                    'driver' => $credentials['driver']
                ],
                'tables' => $this->configureTables($tables),
            ],
            'storage' => $this->configureInputMapping($tables)
        ];

        if ($this->driver == 'redshift' && isset($credentials['schema'])) {
            $configuration['parameters']['db']['schema'] = $credentials['schema'];
        }

        if ($credentials['driver'] == 'mysql') {
            if (isset($credentials['ssl'])) {
                $configuration['parameters']['db']['ssl'] = $credentials['ssl'];
                $configuration['parameters']['db']['ssl']['enabled'] = true;
            }
        }

        return $configuration;
    }

    protected function configureTables($tables)
    {
        return array_map(function ($table) {
            return [
                'dbName' => $table['name'],
                'export' => boolval($table['export']),
                'tableId' => $table['id'],
                'items' => array_map(function ($column) {
                    return [
                        'name' => $column['name'],
                        'dbName' => $column['dbName'],
                        'type' => $column['type'],
                        'size' => $column['size'],
                        'nullable' => boolval($column['null']),
                        'default' => $column['default']
                    ];
                }, $table['columns'])
            ];
        }, $tables);
    }

    protected function configureInputMapping($tables)
    {
        return ['input' => ['tables' => array_map(function ($table) {
            return [
                'source' => $table['id'],
                'destination' => $table['id'] . '.csv',
                'columns' => array_map(function ($column) {
                    return $column['name'];
                }, $table['columns'])
            ];
        }, $tables)]];
    }
}
