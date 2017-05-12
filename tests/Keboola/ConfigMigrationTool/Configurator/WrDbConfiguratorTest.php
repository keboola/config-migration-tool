<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 09/01/17
 * Time: 16:52
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Configurator\WrDbConfigurator;
use Keboola\StorageApi\Options\Components\Configuration;

class WrDbConfiguratorTest extends \PHPUnit_Framework_TestCase
{
    private function createOldConfig()
    {
        return [
            'writerId' => 'migration',
            'writer' => 'db',
            'driver' => 'mysql',
            'description' => 'mysql db writer'
        ];
    }

    public function testCreate()
    {
        $config = $this->createOldConfig();
        $configurator = new WrDbConfigurator();
        $result = $configurator->create($config, 'migration');
        $this->assertInstanceOf(Configuration::class, $result);
        $this->assertEquals('keboola.wr-db-mysql', $result->getComponentId());
        $this->assertEquals($config['writerId'], $result->getConfigurationId());
        $this->assertEquals($config['writerId'], $result->getName());
        $this->assertEquals($config['description'], $result->getDescription());
    }

    public function testConfigure()
    {
        $config = $this->createOldConfig();
        $configurator = new WrDbConfigurator();
        $configurator->create($config, 'migration');

        $credentials = [
            'database' => 'wrdb_test',
            'driver' => 'mysql',
            'host' => 'hostname',
            'password' => 'password',
            'port' => '3306',
            'user' => 'root'
        ];

        $tables = [[
            "id" => "in.c-academy.vouchers",
            "bucket" => "in.c-academy",
            "name" => "vouchers",
            "export" => true,
            "columns" => [
                [
                    "name" => "id",
                    "dbName" => "id",
                    "type" => "INT",
                    "size" => "",
                    "null" => "",
                    "default" => ""
                ],
                [
                    "name" => "value",
                    "dbName" => "value",
                    "type" => "INT",
                    "size" => "",
                    "null" => 1,
                    "default" => ""
                ],
                [
                    "name" => "idUser",
                    "dbName" => "idUser",
                    "type" => "INT",
                    "size" => "",
                    "null" => 1,
                    "default" => ""
                ],
                [
                    "name" => "code",
                    "dbName" => "code",
                    "type" => "IGNORE",
                    "size" => "255",
                    "null" => "",
                    "default" => ""
                ]
            ],
            "primaryKey" => ["id"]
        ]];

        $result = $configurator->configure($credentials, $tables);

        $this->assertArrayHasKey('parameters', $result);
        $parameters = $result['parameters'];
        $this->assertArrayHasKey('db', $parameters);
        $credentials = $parameters['db'];
        $this->assertArrayHasKey('tables', $parameters);
        $table = $parameters['tables'][0];

        $this->assertEquals('hostname', $credentials['host']);
        $this->assertEquals('3306', $credentials['port']);
        $this->assertEquals('wrdb_test', $credentials['database']);
        $this->assertEquals('root', $credentials['user']);
        $this->assertEquals('password', $credentials['#password']);
        $this->assertEquals('mysql', $credentials['driver']);

        $this->assertArrayHasKey('dbName', $table);
        $this->assertArrayHasKey('export', $table);
        $this->assertArrayHasKey('tableId', $table);
        $this->assertArrayHasKey('items', $table);

        $column = $table['items'][0];
        $this->assertArrayHasKey('name', $column);
        $this->assertArrayHasKey('dbName', $column);
        $this->assertArrayHasKey('type', $column);
        $this->assertArrayHasKey('size', $column);
        $this->assertArrayHasKey('nullable', $column);
        $this->assertArrayHasKey('default', $column);

        $this->assertArrayHasKey('storage', $result);
        $this->assertArrayHasKey('input', $result['storage']);
        $this->assertArrayHasKey('tables', $result['storage']['input']);

        $this->assertNotEmpty($result['storage']['input']['tables']);
        $inputMappingTable = array_shift($result['storage']['input']['tables']);
        $this->assertArrayHasKey('source', $inputMappingTable);
        $this->assertArrayHasKey('destination', $inputMappingTable);
        $this->assertArrayHasKey('columns', $inputMappingTable);

        $this->assertEquals('in.c-academy.vouchers', $inputMappingTable['source']);
        $this->assertEquals('in.c-academy.vouchers.csv', $inputMappingTable['destination']);
        $this->assertContains('id', $inputMappingTable['columns']);
        $this->assertContains('idUser', $inputMappingTable['columns']);
        $this->assertContains('value', $inputMappingTable['columns']);
        $this->assertNotContains('code', $inputMappingTable['columns']);
    }
}
