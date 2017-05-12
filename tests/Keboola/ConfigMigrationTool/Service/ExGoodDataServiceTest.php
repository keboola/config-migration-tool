<?php
/**
 * @copy Keboola 2016
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Test\Service;

use Keboola\ConfigMigrationTool\Service\ExGoodDataService;
use Keboola\ConfigMigrationTool\Test\ExGoodDataTest;
use Monolog\Logger;

class ExGoodDataServiceTest extends ExGoodDataTest
{
    /** @var ExGoodDataService */
    private $service;

    public function setUp()
    {
        parent::setUp();
        $this->service = new ExGoodDataService(new Logger(APP_NAME));
    }

    public function testGetConfigs()
    {
        $oldConfigIds = $this->createOldConfigs();
        $configs = $this->service->getConfigs();

        $testConfigs = array_filter($configs, function ($item) {
            return (false !== strstr($item['id'], 'migrationtest'));
        });

        foreach ($testConfigs as $k => $v) {
            $testConfigs[$v['id']] = $v;
            unset($testConfigs[$k]);
        }

        foreach ($oldConfigIds as $id) {
            $this->assertArrayHasKey($id, $testConfigs);
            $this->assertEquals($id, $testConfigs[$id]['id']);
            $this->assertEquals($id, $testConfigs[$id]['name']);
        }
    }

    public function testGetReports()
    {
        $oldConfigIds = $this->createOldConfigs();
        $result = $this->service->getReports(array_shift($oldConfigIds));

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('pid', $result[0]);
        $this->assertArrayHasKey('title', $result[0]);
        $this->assertArrayHasKey('uri', $result[0]);
    }
}
