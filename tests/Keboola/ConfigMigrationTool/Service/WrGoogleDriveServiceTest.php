<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Service;

use Keboola\ConfigMigrationTool\Service\WrGoogleDriveService;
use Keboola\ConfigMigrationTool\Test\WrGoogleDriveTest;

class WrGoogleDriveServiceTest extends WrGoogleDriveTest
{
    /** @var WrGoogleDriveService */
    private $service;

    public function setUp() : void
    {
        parent::setUp();
        $this->service = new WrGoogleDriveService();
    }

    public function testGetConfigs() : void
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

    public function testGetAccount() : void
    {
        $oldConfigIds = $this->createOldConfigs();

        $account = $this->service->getAccount(array_shift($oldConfigIds));

        $this->assertArrayHasKey('id', $account);
        $this->assertArrayHasKey('name', $account);
        $this->assertArrayHasKey('description', $account);
        $this->assertArrayHasKey('googleName', $account);
        $this->assertArrayHasKey('googleId', $account);
        $this->assertArrayHasKey('email', $account);
        $this->assertArrayHasKey('accessToken', $account);
        $this->assertArrayHasKey('refreshToken', $account);
        $this->assertArrayHasKey('items', $account);
    }
}
