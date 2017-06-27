<?php
/**
 * Author: miro@keboola.com
 * Date: 13/06/2017
 */

namespace Keboola\ConfigMigrationTool\Test\Configurator;

use Keboola\ConfigMigrationTool\Configurator\WrGoogleDriveConfigurator;
use Keboola\StorageApi\Options\Components\Configuration;

class WrGoogleDriveConfiguratorTest extends \PHPUnit_Framework_TestCase
{
    private function createOldConfig()
    {
        return [
            'name' => 'Academy',
            'description' => 'academy google drive writer',
            'id' => 'academy',
            'googleId' => '123456',
            'googleName' => 'Miro Cillik',
            'email' => 'test',
            'accessToken' => '12345',
            'refreshToken' => '67890'
        ];
    }

    public function testCreate()
    {
        $config = $this->createOldConfig();
        $configurator = new WrGoogleDriveConfigurator();
        $result = $configurator->create($config);
        $this->assertInstanceOf(Configuration::class, $result);
        $this->assertEquals('keboola.wr-google-drive', $result->getComponentId());
        $this->assertEquals($config['id'], $result->getConfigurationId());
        $this->assertEquals($config['name'], $result->getName());
        $this->assertEquals($config['description'], $result->getDescription());
    }

    public function testConfigure()
    {
        $config = $this->createOldConfig();
        $config['items'] = [[
            'id' => '244073864',
            'title' => 'Vouchers',
            'googleId' => '0B8ceg4OWLR3lWFhNREJEVmFxX0E',
            'type' => 'file',
            'sheetId' => '',
            'tableId' => 'in.c-academy.vouchers',
            'operation' => 'update',
            'targetFolder' => '0B8ceg4OWLR3ld0czTWxfd3RmQnc',
            'folder' => ['id' => '0B8ceg4OWLR3ld0czTWxfd3RmQnc']
        ]];

        $configurator = new WrGoogleDriveConfigurator();
        $configurator->create($config);
        $result = $configurator->configure($config);

        $this->assertArrayHasKey('parameters', $result);
        $parameters = $result['parameters'];
        $this->assertArrayHasKey('tables', $parameters);
        $table = $parameters['tables'][0];

        $this->assertArrayHasKey('id', $table);
        $this->assertArrayHasKey('fileId', $table);
        $this->assertArrayHasKey('title', $table);
        $this->assertArrayHasKey('enabled', $table);
        $this->assertArrayHasKey('folder', $table);
        $this->assertArrayHasKey('id', $table['folder']);
        $this->assertArrayHasKey('action', $table);
        $this->assertArrayHasKey('tableId', $table);
        $this->assertArrayHasKey('convert', $table);

        $this->assertArrayHasKey('storage', $result);
        $this->assertArrayHasKey('input', $result['storage']);
        $this->assertArrayHasKey('tables', $result['storage']['input']);

        $this->assertNotEmpty($result['storage']['input']['tables']);
        $inputMappingTable = array_shift($result['storage']['input']['tables']);
        $this->assertArrayHasKey('source', $inputMappingTable);
        $this->assertArrayHasKey('destination', $inputMappingTable);

        $this->assertEquals('in.c-academy.vouchers', $inputMappingTable['source']);
        $this->assertEquals('in.c-academy.vouchers.csv', $inputMappingTable['destination']);
    }
}
