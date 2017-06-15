<?php
/**
 * Author: miro@keboola.com
 * Date: 13/06/2017
 */

namespace Keboola\ConfigMigrationTool\Test\Configurator;

use Keboola\ConfigMigrationTool\Configurator\WrGoogleSheetsConfigurator;
use Keboola\StorageApi\Options\Components\Configuration;

class WrGoogleSheetsConfiguratorTest extends \PHPUnit_Framework_TestCase
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
        $configurator = new WrGoogleSheetsConfigurator();
        $result = $configurator->create($config);
        $this->assertInstanceOf(Configuration::class, $result);
        $this->assertEquals('keboola.wr-google-sheets', $result->getComponentId());
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
            'type' => 'sheet',
            'sheetId' => 'o5si9ec',
            'sheetTitle' => 'vouchers',
            'tableId' => 'in.c-academy.vouchers',
            'operation' => 'update',
            'targetFolder' => '0B8ceg4OWLR3ld0czTWxfd3RmQnc'
        ]];

        $configurator = new WrGoogleSheetsConfigurator();
        $configurator->create($config);
        $result = $configurator->configure($config);

        $this->assertArrayHasKey('parameters', $result);
        $parameters = $result['parameters'];
        $this->assertArrayHasKey('tables', $parameters);
        $table = $parameters['tables'][0];

        $this->assertArrayHasKey('id', $table);
        $this->assertArrayHasKey('action', $table);
        $this->assertArrayHasKey('sheetTitle', $table);
        $this->assertArrayHasKey('enabled', $table);
        $this->assertArrayHasKey('tableId', $table);
        $this->assertArrayHasKey('title', $table);
        $this->assertArrayHasKey('fileId', $table);
        $this->assertArrayHasKey('sheetId', $table);
        $this->assertArrayHasKey('folder', $table);
        $this->assertArrayHasKey('id', $table['folder']);

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
