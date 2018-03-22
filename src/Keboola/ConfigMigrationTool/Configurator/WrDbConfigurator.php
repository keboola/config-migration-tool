<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Configurator;

use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\StorageApi\Options\Components\Configuration;

class WrDbConfigurator
{
    /** @var string */
    protected $driver;

    public function __construct(string $driver = 'mysql')
    {
        $this->driver = $driver;
    }

    public function create(array $attributes, string $prettyName) : Configuration
    {
        $configuration = new Configuration();
        $configuration->setComponentId($this->getComponentId());
        $configuration->setConfigurationId($attributes['writerId']);
        $configuration->setName($prettyName);
        $configuration->setDescription(isset($attributes['description'])?$attributes['description']:'');

        return $configuration;
    }

    public function getComponentId() : string
    {
        return ($this->driver == 'redshift')?'keboola.wr-redshift-v2':'keboola.wr-db-' . $this->driver;
    }

    /**
     * @param array $credentials
     * @param array $tables
     * @return array
     * @throws ApplicationException
     */
    public function configure(array $credentials, array $tables) : array
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
                    'driver' => $credentials['driver'],
                ],
                'tables' => $this->configureTables($tables),
            ],
            'storage' => $this->configureInputMapping($tables),
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

    protected function configureTables(array $tables) : array
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
                        'default' => $column['default'],
                    ];
                }, $table['columns']),
            ];
        }, $tables);
    }

    protected function configureInputMapping(array $tables) : array
    {
        return ['input' => ['tables' => array_map(
            function ($table) {
                return [
                    'source' => $table['id'],
                    'destination' => $table['id'] . '.csv',
                    'columns' => array_map(
                        function ($column) {
                            return $column['name'];
                        },
                        array_filter(
                            $table['columns'],
                            function ($column) {
                                return (strtolower($column['type']) !== 'ignore');
                            }
                        )
                    ),
                ];
            },
            $tables
        )]];
    }
}
