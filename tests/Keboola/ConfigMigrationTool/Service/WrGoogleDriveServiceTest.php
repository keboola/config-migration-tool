<?php
/**
 * Author: miro@keboola.com
 * Date: 13/06/2017
 */

namespace Keboola\ConfigMigrationTool\Test\Service;

use Keboola\ConfigMigrationTool\Service\WrGoogleDriveService;
use Keboola\ConfigMigrationTool\Test\WrGoogleDriveTest;
use Monolog\Logger;

class WrGoogleDriveServiceTest extends WrGoogleDriveTest
{
    /** @var WrGoogleDriveService */
    private $service;

    public function setUp()
    {
        parent::setUp();
        $this->service = new WrGoogleDriveService(new Logger(APP_NAME));
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