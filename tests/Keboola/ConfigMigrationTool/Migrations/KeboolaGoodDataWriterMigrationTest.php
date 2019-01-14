<?php

/** @noinspection PhpParamsInspection */

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Migration\KeboolaGoodDataWriterMigration;
use Keboola\ConfigMigrationTool\Service\GoodDataProvisioningService;
use Keboola\ConfigMigrationTool\Service\GoodDataService;
use Keboola\ConfigMigrationTool\Service\LegacyGoodDataWriterService;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class KeboolaGoodDataWriterMigrationTest extends TestCase
{
    /** @var array */
    private $oldConfig;

    /** @var string */
    private $originComponentId;

    /** @var string */
    private $destinationComponentId;

    /** @var Client */
    private $storageApiClient;

    /** @var Components */
    private $components;

    /** @var GoodDataService */
    private $goodData;


    public function setUp() : void
    {
        parent::setUp();

        $this->originComponentId = 'gooddata-writer';
        $this->destinationComponentId = 'keboola.gooddata-writer';

        $configurationBody = [
            'user' => [
                'uid' => 'uid',
                'login' => getenv('WRGD_LOGIN'),
                'password' => getenv('WRGD_PASSWORD'),
            ],
            'project' => [
                'pid' => getenv('WRGD_PID'),
            ],
            'domain' => [
                'url' => 'https://secure.gooddata.com',
            ],
            'dimensions' => [
                'd1' => [
                    'title' => uniqid(),
                    'includeTime' => true,
                    'isExported' => true,
                ],
            ],
        ];
        $tableConfiguration1 = [
            'title' => uniqid(),
            'export' => 1,
            'isExported' => 1,
            'grain' => 'c1,c2,c3',
            'ignoreFilter' => 1,
            'anchorIdentifier' => uniqid(),
            'columns' => [
                'c1' => [
                    'type' => 'ATTRIBUTE',
                    'title' => uniqid(),
                    'identifier' => uniqid(),
                    'sortLabel' => 'c3',
                    'sortOrder' => 'DESC',
                ],
                'c2' => [
                    'type' => 'FACT',
                    'title' => uniqid(),
                    'dataType' => 'DECIMAL',
                    'dataTypeSize' => '8,3',
                ],
                'c3' => [
                    'type' => 'LABEL',
                    'title' => uniqid(),
                    'reference' => 'c1',
                ],
                'c4' => [
                    'type' => 'DATE',
                    'format' => 'yyyy-MM-dd HH:mm:ss',
                    'dateDimension' => 'd1',
                ],
                'c5' => [
                    'type' => 'REFERENCE',
                    'schemaReference' => 't2',
                ],
            ],
        ];
        $tableConfiguration2 = [
            'title' => uniqid(),
            'export' => 1,
            'columns' => [
                'c21' => [
                    'type' => 'CONNECTION_POINT',
                    'title' => uniqid(),
                ],
                'c22' => [
                    'type' => 'FACT',
                    'title' => uniqid(),
                ],
            ],
            'incrementalLoad' => 3,
        ];
        $tableConfiguration3 = [
            'title' => uniqid(),
            'columns' => [
                'c31' => [
                    'type' => 'IGNORE',
                ],
            ],
            'export' => 0,
        ];

        $configId = uniqid('migrationtest-wrgd-');
        $this->oldConfig = [
            'id' => $configId,
            'configuration' => $configurationBody,
            'rows' => [
                [
                    'id' => 't1',
                    'configuration' => $tableConfiguration1,
                ],
                [
                    'id' => 't2',
                    'configuration' => $tableConfiguration2,
                ],
                [
                    'id' => 't3',
                    'configuration' => $tableConfiguration3,
                ],
            ],
        ];

        $this->storageApiClient = new Client(['token' => getenv('KBC_TOKEN'), 'url' => getenv('KBC_URL')]);
        $this->components = new Components($this->storageApiClient);

        $c = new Configuration();
        $c->setComponentId($this->originComponentId);
        $c->setConfigurationId($this->oldConfig['id']);
        $c->setName($this->oldConfig['id']);
        $c->setDescription('Migrate this account');
        $c->setConfiguration($this->oldConfig['configuration']);
        $this->components->addConfiguration($c);

        $r = new ConfigurationRow($c);
        $r->setRowId('t1');
        $r->setConfiguration($this->oldConfig['rows'][0]['configuration']);
        $this->components->addConfigurationRow($r);

        $r = new ConfigurationRow($c);
        $r->setRowId('t2');
        $r->setConfiguration($this->oldConfig['rows'][1]['configuration']);
        $this->components->addConfigurationRow($r);


        $this->goodData = new GoodDataService();
        $this->goodData->login(
            getenv('WRGD_GOODDATA_URI'),
            getenv('WRGD_LOGIN'),
            getenv('WRGD_PASSWORD')
        );
    }

    public function testTransformConfiguration() : void
    {
        $migration = new KeboolaGoodDataWriterMigration(new Logger(APP_NAME));
        $result = $migration->transformConfiguration($this->oldConfig);

        $this->assertArrayHasKey('user', $result['configuration']['parameters']);
        $this->assertArrayNotHasKey('password', $result['configuration']['parameters']['user']);
        $this->assertArrayHasKey('#password', $result['configuration']['parameters']['user']);
        $this->assertArrayHasKey('login', $result['configuration']['parameters']['user']);
        $this->assertArrayHasKey('project', $result['configuration']['parameters']);
        $this->assertArrayHasKey('pid', $result['configuration']['parameters']['project']);
        $this->assertArrayHasKey('backendUrl', $result['configuration']['parameters']['project']);
        $this->assertEquals(
            $this->oldConfig['configuration']['domain']['url'],
            $result['configuration']['parameters']['project']['backendUrl']
        );
        $this->assertArrayNotHasKey('domain', $result['configuration']['parameters']);

        $this->assertArrayHasKey('tables', $result['configuration']['parameters']);
        $this->assertCount(3, $result['configuration']['parameters']['tables']);
        $this->assertArrayHasKey('columns', $result['configuration']['parameters']['tables']['t1']);
        $this->assertCount(5, $result['configuration']['parameters']['tables']['t1']['columns']);
        $this->assertArrayHasKey('columns', $result['configuration']['parameters']['tables']['t2']);
        $this->assertCount(2, $result['configuration']['parameters']['tables']['t2']['columns']);
        $this->assertCount(0, $result['rows']);

        $this->assertArrayHasKey('grain', $result['configuration']['parameters']['tables']['t1']);
        $this->assertEquals(['c1', 'c2', 'c3'], $result['configuration']['parameters']['tables']['t1']['grain']);

        $this->assertArrayHasKey('storage', $result['configuration']);
        $this->assertArrayHasKey('input', $result['configuration']['storage']);
        $this->assertArrayHasKey('tables', $result['configuration']['storage']['input']);
        $this->assertCount(3, $result['configuration']['storage']['input']['tables']);

        $this->assertArrayHasKey('source', $result['configuration']['storage']['input']['tables'][0]);
        $this->assertEquals('t1', $result['configuration']['storage']['input']['tables'][0]['source']);
        $this->assertArrayHasKey('columns', $result['configuration']['storage']['input']['tables'][0]);
        $this->assertCount(5, $result['configuration']['storage']['input']['tables'][0]['columns']);
        $this->assertArrayNotHasKey('limit', $result['configuration']['storage']['input']['tables'][0]);
        $this->assertArrayNotHasKey('changed_since', $result['configuration']['storage']['input']['tables'][0]);

        $this->assertEquals('t2', $result['configuration']['storage']['input']['tables'][1]['source']);
        $this->assertArrayNotHasKey('limit', $result['configuration']['storage']['input']['tables'][1]);
        $this->assertArrayHasKey('changed_since', $result['configuration']['storage']['input']['tables'][1]);
        $this->assertEquals('-3 days', $result['configuration']['storage']['input']['tables'][1]['changed_since']);

        $this->assertEquals('t3', $result['configuration']['storage']['input']['tables'][2]['source']);
        $this->assertArrayHasKey('limit', $result['configuration']['storage']['input']['tables'][2]);
        $this->assertEquals(1, $result['configuration']['storage']['input']['tables'][2]['limit']);
        $this->assertArrayNotHasKey('changed_since', $result['configuration']['storage']['input']['tables'][2]);
    }

    public function testCheckGoodDataConfigurationValid() : void
    {
        $migration = new KeboolaGoodDataWriterMigration(new Logger(APP_NAME));
        $newConfig = $migration->transformConfiguration($this->oldConfig);
        $migration->checkGoodDataConfiguration($newConfig);
        $this->assertTrue(true);
    }

    public function testCheckGoodDataConfigurationMissingPid() : void
    {
        $migration = new KeboolaGoodDataWriterMigration(new Logger(APP_NAME));
        $newConfig = $migration->transformConfiguration($this->oldConfig);
        unset($newConfig['configuration']['parameters']['project']);

        $this->expectException(UserException::class);
        $migration->checkGoodDataConfiguration($newConfig);
    }

    public function testCheckGoodDataConfigurationMissingLogin() : void
    {
        $migration = new KeboolaGoodDataWriterMigration(new Logger(APP_NAME));
        $newConfig = $migration->transformConfiguration($this->oldConfig);
        unset($newConfig['configuration']['parameters']['user']['login']);

        $this->expectException(UserException::class);
        $migration->checkGoodDataConfiguration($newConfig);
    }

    public function testCheckGoodDataConfigurationMissingPassword() : void
    {
        $migration = new KeboolaGoodDataWriterMigration(new Logger(APP_NAME));
        $newConfig = $migration->transformConfiguration($this->oldConfig);
        unset($newConfig['configuration']['parameters']['user']['#password']);

        $this->expectException(UserException::class);
        $migration->checkGoodDataConfiguration($newConfig);
    }

    public function testGetAuthTokenFromProjectMeta() : void
    {
        $migration = new KeboolaGoodDataWriterMigration(new Logger(APP_NAME));
        $migration->setImageParameters(['#production_token' => 'tp1', '#demo_token' => 'td1']);

        $token = $migration->getAuthTokenFromProjectMeta(['content' => ['authorizationToken' => 'tp1']]);
        $this->assertEquals('production', $token);

        $token = $migration->getAuthTokenFromProjectMeta(['content' => ['authorizationToken' => 'td1']]);
        $this->assertEquals('demo', $token);

        $token = $migration->getAuthTokenFromProjectMeta(['content' => ['authorizationToken' => 'customToken']]);
        $this->assertEquals('customToken', $token);
    }

    public function testGetProjectToProvisioningParams() : void
    {
        $migration = new KeboolaGoodDataWriterMigration(new Logger(APP_NAME));
        $migration->setImageParameters(['gooddata_provisioning_url' => 'url']);
        $newConfig = $migration->transformConfiguration($this->oldConfig);

        $result = $migration->getAddProjectToProvisioningParams($newConfig, 'demo');
        $this->assertArrayHasKey('pid', $result);
        $this->assertEquals(getenv('WRGD_PID'), $result['pid']);
        $this->assertArrayHasKey('keboolaToken', $result['params']);
        $this->assertEquals('demo', $result['params']['keboolaToken']);

        $result = $migration->getAddProjectToProvisioningParams($newConfig, 'production');
        $this->assertArrayHasKey('keboolaToken', $result['params']);
        $this->assertEquals('production', $result['params']['keboolaToken']);

        $result = $migration->getAddProjectToProvisioningParams($newConfig, 'token123');
        $this->assertArrayHasKey('customToken', $result['params']);
        $this->assertEquals('token123', $result['params']['customToken']);
    }

    public function testGetProjectMeta() : void
    {
        $migration = new KeboolaGoodDataWriterMigration(new Logger(APP_NAME));
        $migration->setImageParameters(['gooddata_url' => getenv('WRGD_GOODDATA_URI')]);
        $newConfig = $migration->transformConfiguration($this->oldConfig);

        $migration->setGoodData($this->goodData);
        $result = $migration->getProjectMeta($newConfig);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('state', $result['content']);
        $this->assertEquals('ENABLED', $result['content']['state']);
        $this->assertArrayHasKey('links', $result);
        $this->assertArrayHasKey('self', $result['links']);
        $this->assertEquals('/gdc/projects/' . getenv('WRGD_PID'), $result['links']['self']);
    }

    private function initMigration() : KeboolaGoodDataWriterMigration
    {
        $migration = new KeboolaGoodDataWriterMigration(new Logger(APP_NAME));
        $migration
            ->setOriginComponentId($this->originComponentId)
            ->setDestinationComponentId($this->destinationComponentId);
        $migration->setImageParameters([
            'gooddata_provisioning_url' => 'https://provisioning',
            'gooddata_url' => 'https://secure.gooddata.com',
            '#production_token' => 'KB_PROD',
            '#demo_token' => 'KB_DEMO',
            'project_access_domain' => 'test.keboola.com',
        ]);

        return $migration;
    }

    private function updateConfiguration(array $params) : void
    {
        $config = array_replace_recursive($this->oldConfig['configuration'], $params);
        $c = new Configuration();
        $c->setComponentId($this->originComponentId);
        $c->setConfigurationId($this->oldConfig['id']);
        $c->setName($this->oldConfig['id']);
        $c->setDescription('Migrate this account');
        $c->setConfiguration($config);
        $this->components->updateConfiguration($c);
    }

    private function getGoodDataMock(?array $getProjectResult = null) : MockObject
    {
        $mock = $this->createMock(GoodDataService::class);
        $mock->method('login')->willReturn(null);
        $mock->method('getProject')->willReturn($getProjectResult);
        return $mock;
    }

    private function getLegacyWriterMock(?array $listUsersResult = []) : MockObject
    {
        $mock = $this->createMock(LegacyGoodDataWriterService::class);
        $mock->method('listUsers')->willReturn($listUsersResult);
        $mock->expects($this->once())->method('listUsers');
        return $mock;
    }

    private function getProvisioningMock() : MockObject
    {
        $mock = $this->createMock(GoodDataProvisioningService::class);
        $mock->method('addProject')->willReturn(null);
        $mock->method('addUser')->willReturn(null);
        $mock->method('getProductionProjectsCount')->willReturn(0);
        return $mock;
    }

    private function getManageApiMock() : MockObject
    {
        $mock = $this->createMock(\Keboola\ManageApi\Client::class);
        $mock->method('setProjectLimits');
        return $mock;
    }

    public function testExecute() : void
    {
        $login = getenv('KBC_PROJECTID') . "-" . $this->oldConfig['id'] . '@test.keboola.com';
        $this->updateConfiguration(['user' => ['login' => $login]]);

        $migration = $this->initMigration();
        $migration->setManageApi($this->getManageApiMock());
        $migration->setGoodData($this->getGoodDataMock(
            ['content' =>  ['authorizationToken' => 'KB_DEMO', 'state' => 'ENABLED']]
        ));
        $migration->setLegacyWriter($this->getLegacyWriterMock([[
            'email' => getenv('KBC_PROJECTID') . "-" . $this->oldConfig['id'] . '@test.keboola.com',
            'uid' => uniqid(),
        ]]));

        $provisioningMock = $this->getProvisioningMock();
        $provisioningMock->expects($this->once())->method('addProject');
        $provisioningMock->expects($this->once())->method('addUser');
        $migration->setProvisioning($provisioningMock);

        $createdConfigurations = $migration->execute();
        $this->assertNotEmpty($createdConfigurations);
        $this->assertGreaterThanOrEqual(1, count($createdConfigurations));

        $originConfig1 = $this->components->getConfiguration($this->originComponentId, $this->oldConfig['id']);
        $this->assertArrayHasKey('migrationStatus', $originConfig1['configuration']);
        $this->assertEquals('success', $originConfig1['configuration']['migrationStatus']);

        $destConfig1 = $this->components->getConfiguration($this->destinationComponentId, $this->oldConfig['id']);
        $this->assertNotEmpty($destConfig1);
        $this->assertArrayHasKey('storage', $destConfig1['configuration']);
        $this->assertArrayHasKey('input', $destConfig1['configuration']['storage']);
        $this->assertArrayHasKey('tables', $destConfig1['configuration']['storage']['input']);
        $this->assertCount(2, $destConfig1['configuration']['storage']['input']['tables']);
    }

    public function testExecuteDoNotAddCustomTokenProjectToProvisioning() : void
    {
        $login = getenv('KBC_PROJECTID') . "-" . $this->oldConfig['id'] . '@test.keboola.com';
        $this->updateConfiguration(['user' => ['login' => $login]]);

        $migration = $this->initMigration();
        $migration->setManageApi($this->getManageApiMock());
        $migration->setGoodData($this->getGoodDataMock(
            ['content' => ['authorizationToken' => 'custom', 'state' => 'ENABLED']]
        ));
        $migration->setLegacyWriter($this->getLegacyWriterMock([[
            'email' => getenv('KBC_PROJECTID') . "-" . $this->oldConfig['id'] . '@test.keboola.com',
            'uid' => uniqid(),
        ]]));

        $provisioningMock = $this->getProvisioningMock();
        $provisioningMock->expects($this->once())->method('addProject')->with(
            $this->equalTo(getenv('WRGD_PID')),
            $this->equalTo(['customToken' => 'custom'])
        );
        $provisioningMock->expects($this->once())->method('addUser');
        $migration->setProvisioning($provisioningMock);

        $createdConfigurations = $migration->execute();
        $this->assertNotEmpty($createdConfigurations);
        $this->assertGreaterThanOrEqual(1, count($createdConfigurations));
    }

    public function testExecuteDoNotAddForeignUserToProvisioning() : void
    {
        $login = uniqid() . '@test.keboola.com';
        $this->updateConfiguration(['user' => ['login' => $login]]);

        $migration = $this->initMigration();
        $migration->setManageApi($this->getManageApiMock());
        $migration->setGoodData($this->getGoodDataMock(
            ['content' => ['authorizationToken' => 'KB_PROD', 'state' => 'ENABLED']]
        ));
        $migration->setLegacyWriter($this->getLegacyWriterMock([[
            'email' => $login,
            'uid' => uniqid(),
        ]]));

        $provisioningMock = $this->getProvisioningMock();
        $provisioningMock->expects($this->once())->method('addProject')->with(
            $this->equalTo(getenv('WRGD_PID')),
            $this->equalTo(['keboolaToken' => 'production'])
        );
        $provisioningMock->expects($this->never())->method('addUser');
        $migration->setProvisioning($provisioningMock);

        $createdConfigurations = $migration->execute();
        $this->assertNotEmpty($createdConfigurations);
        $this->assertGreaterThanOrEqual(1, count($createdConfigurations));
    }

    public function tearDown() : void
    {
        parent::tearDown();
        try {
            $this->components->deleteConfiguration($this->originComponentId, $this->oldConfig['id']);
        } catch (ClientException $e) {
        }
        try {
            $this->components->deleteConfiguration($this->destinationComponentId, $this->oldConfig['id']);
        } catch (ClientException $e) {
        }
    }
}
