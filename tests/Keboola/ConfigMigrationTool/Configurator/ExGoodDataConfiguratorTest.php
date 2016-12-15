<?php
/**
 * @copy Keboola 2016
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Configurator\ExGoodDataConfigurator;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\StorageApi\Options\Components\Configuration;

class ExGoodDataConfiguratorTest extends \PHPUnit_Framework_TestCase
{
    public function testExGoodDataConfiguratorTestCreate()
    {
        $config = [
            'id' => uniqid(),
            'name' => uniqid(),
            'description' => uniqid()
        ];
        $configurator = new ExGoodDataConfigurator();
        $result = $configurator->create($config);
        $this->assertInstanceOf(Configuration::class, $result);
        $this->assertEquals('keboola.ex-gooddata', $result->getComponentId());
        $this->assertEquals($config['id'], $result->getConfigurationId());
        $this->assertEquals($config['name'], $result->getName());
        $this->assertEquals($config['description'], $result->getDescription());
    }

    public function testExGoodDataConfiguratorTestConfigureSuccess()
    {
        $pid = uniqid();
        $writers = [$pid => uniqid()];
        $reports = [uniqid(), uniqid()];
        $reportsConfig = [
            [
                'uri' => $reports[0],
                'pid' => $pid
            ],
            [
                'uri' => $reports[1],
                'pid' => $pid
            ]
        ];
        $configurator = new ExGoodDataConfigurator();
        $result = $configurator->configure($writers, $reportsConfig);
        $this->assertArrayHasKey('parameters', $result);
        $this->assertArrayHasKey('writer_id', $result['parameters']);
        $this->assertArrayHasKey('reports', $result['parameters']);
        $this->assertEquals($writers[$pid], $result['parameters']['writer_id']);
        $this->assertEquals($reports, $result['parameters']['reports']);
    }

    public function testExGoodDataConfiguratorTestConfigureErrorWrongPid()
    {
        $pid = uniqid();
        $writers = [$pid => uniqid()];
        $reports = [uniqid(), uniqid()];
        $reportsConfig = [
            [
                'uri' => $reports[0],
                'pid' => uniqid()
            ],
            [
                'uri' => $reports[1],
                'pid' => $pid
            ]
        ];
        $configurator = new ExGoodDataConfigurator();
        try {
            $configurator->configure($writers, $reportsConfig);
            $this->fail();
        } catch (UserException $e) {
            $this->assertTrue(true);
        }
    }

    public function testExGoodDataConfiguratorTestConfigureErrorTwoWriters()
    {
        $pid = uniqid();
        $pid2 = uniqid();
        $writers = [$pid => uniqid(), $pid2 => uniqid()];
        $reports = [uniqid(), uniqid()];
        $reportsConfig = [
            [
                'uri' => $reports[0],
                'pid' => $pid
            ],
            [
                'uri' => $reports[1],
                'pid' => $pid2
            ]
        ];
        $configurator = new ExGoodDataConfigurator();
        try {
            $configurator->configure($writers, $reportsConfig);
            $this->fail();
        } catch (UserException $e) {
            $this->assertTrue(true);
        }
    }
}
