<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 01/07/16
 * Time: 10:10
 */
namespace Keboola\ConfigMigrationTool\Test\Service;

use Keboola\ConfigMigrationTool\Service\ExGoogleAnalyticsService;
use Keboola\ConfigMigrationTool\Test\ExGoogleAnalyticsTest;
use Monolog\Logger;

class ExGoogleAnalyticsServiceTest extends ExGoogleAnalyticsTest
{
    /** @var ExGoogleAnalyticsService */
    private $service;

    public function setUp()
    {
        parent::setUp();
        $this->service = new ExGoogleAnalyticsService(new Logger(APP_NAME));
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

    public function testGetAccount()
    {
        $oldConfigIds = $this->createOldConfigs();

        $account = $this->service->getAccount(array_shift($oldConfigIds));

        $this->assertArrayHasKey('id', $account);
        $this->assertArrayHasKey('accountName', $account);
        $this->assertArrayHasKey('description', $account);
        $this->assertArrayHasKey('outputBucket', $account);
        $this->assertArrayHasKey('googleId', $account);
        $this->assertArrayHasKey('accessToken', $account);
        $this->assertArrayHasKey('refreshToken', $account);
        $this->assertArrayHasKey('configuration', $account);
        $this->assertArrayHasKey('items', $account);
    }
}
