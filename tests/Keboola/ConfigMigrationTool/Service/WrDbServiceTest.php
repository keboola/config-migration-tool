<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 06/01/17
 * Time: 13:50
 */

namespace Keboola\ConfigMigrationTool\Test\Service;

use Keboola\ConfigMigrationTool\Service\WrDbService;
use Keboola\ConfigMigrationTool\Test\WrDbTest;
use Monolog\Logger;

class WrDbServiceTest extends WrDbTest
{
    /** @var WrDbService */
    private $service;

    public function setUp()
    {
        parent::setUp();
        $this->service = new WrDbService('mysql', new Logger(APP_NAME));
    }

    public function testGetConfigs()
    {
        $this->createOldConfig();
        $configs = $this->service->getConfigs();
        $testConfigs = array_filter($configs, function ($item) {
            return ($item['id'] == 'migration');
        });
        $testConfig = array_shift($testConfigs);

        $this->assertArrayHasKey('id', $testConfig);
        $this->assertEquals('migration', $testConfig['id']);
        $this->assertArrayHasKey('name', $testConfig);
        $this->assertEquals('migration', $testConfig['name']);
    }

    public function testGetConfig()
    {
        $this->createOldConfig();
        $config = $this->service->getConfig('migration');

        $this->assertArrayHasKey('id', $config);
        $this->assertEquals('migration', $config['id']);
        $this->assertArrayHasKey('name', $config);
        $this->assertEquals('migration', $config['name']);
    }

    public function testGetConfigTables()
    {
        $this->createOldConfig();
        $tables = $this->service->getConfigTables('migration');

        $this->assertCount(5, $tables);
        $table = array_shift($tables);

        $this->assertArrayHasKey('id', $table);
        $this->assertArrayHasKey('bucket', $table);
        $this->assertArrayHasKey('name', $table);
        $this->assertArrayHasKey('export', $table);
        $this->assertArrayHasKey('columns', $table);
        $this->assertEquals('in.c-academy.vouchers', $table['id']);
        $this->assertEquals('in.c-academy', $table['bucket']);
        $this->assertEquals('vouchers', $table['name']);
        $this->assertNotEmpty($table['columns']);
    }

    public function testGetCredentials()
    {
        $this->createOldConfig();
        $credentials = $this->service->getCredentials('migration');
        $this->assertArrayHasKey('database', $credentials);
        $this->assertEquals('wrdb_test', $credentials['database']);
        $this->assertArrayHasKey('driver', $credentials);
        $this->assertEquals('mysql', $credentials['driver']);
        $this->assertArrayHasKey('host', $credentials);
        $this->assertEquals('hostname', $credentials['host']);
        $this->assertArrayHasKey('password', $credentials);
        $this->assertEquals('password', $credentials['password']);
        $this->assertArrayHasKey('port', $credentials);
        $this->assertEquals('3306', $credentials['port']);
        $this->assertArrayHasKey('user', $credentials);
        $this->assertEquals('root', $credentials['user']);
    }
}
