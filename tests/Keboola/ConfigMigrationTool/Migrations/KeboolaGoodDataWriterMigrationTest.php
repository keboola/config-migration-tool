<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Migration\KeboolaGoodDataWriterMigration;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class KeboolaGoodDataWriterMigrationTest extends TestCase
{
    /** @var array */
    private $oldConfig;

    ///** @var string */
    //private $originComponentId;

    ///** @var string */
    //private $destinationComponentId;

    public function setUp() : void
    {
        parent::setUp();

        //$this->originComponentId = 'gooddata-writer';
        //$this->destinationComponentId = 'keboola.gooddata-writer';

        $configurationBody = [
            'user' => [
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
            'export' => true,
            'isExported' => true,
            'grain' => 'c1,c2,c3',
            'ignoreFilter' => true,
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
            ],
        ];

        /*$this->storageApiClient = new Client(['token' => getenv('KBC_TOKEN'), 'url' => getenv('KBC_URL')]);
        $this->components = new Components($this->storageApiClient);

        $c = new Configuration();
        $c->setComponentId($this->originComponentId);
        $c->setConfigurationId($this->configuration['id']);
        $c->setName($this->configuration['id']);
        $c->setDescription('Migrate this account');
        $c->setConfiguration($this->configuration['configuration']);
        $this->components->addConfiguration($c);

        $r = new ConfigurationRow($c);
        $r->setRowId('t1');
        $r->setConfiguration($this->configuration['rows'][0]['configuration']);
        $this->components->addConfigurationRow($r);

        $r = new ConfigurationRow($c);
        $r->setRowId('t2');
        $r->setConfiguration($this->configuration['rows'][1]['configuration']);
        $this->components->addConfigurationRow($r);*/
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
        $this->assertCount(2, $result['configuration']['parameters']['tables']);
        $this->assertArrayHasKey('columns', $result['configuration']['parameters']['tables']['t1']);
        $this->assertCount(5, $result['configuration']['parameters']['tables']['t1']['columns']);
        $this->assertArrayHasKey('columns', $result['configuration']['parameters']['tables']['t2']);
        $this->assertCount(2, $result['configuration']['parameters']['tables']['t2']['columns']);
        $this->assertCount(0, $result['rows']);
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
        $migration->setImageParameters(['production_token' => 'tp1', 'demo_token' => 'td1']);

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
        $this->assertArrayHasKey('keboolaToken', $result);
        $this->assertEquals('demo', $result['keboolaToken']);

        $result = $migration->getAddProjectToProvisioningParams($newConfig, 'production');
        $this->assertArrayHasKey('keboolaToken', $result);
        $this->assertEquals('production', $result['keboolaToken']);

        $result = $migration->getAddProjectToProvisioningParams($newConfig, 'token123');
        $this->assertArrayHasKey('customToken', $result);
        $this->assertEquals('token123', $result['customToken']);
    }

    public function testGetProjectMeta() : void
    {
        $migration = new KeboolaGoodDataWriterMigration(new Logger(APP_NAME));
        $migration->setImageParameters(['gooddata_url' => getenv('WRGD_GOODDATA_URI')]);
        $newConfig = $migration->transformConfiguration($this->oldConfig);

        $result = $migration->getProjectMeta($newConfig);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('state', $result['content']);
        $this->assertEquals('ENABLED', $result['content']['state']);
        $this->assertArrayHasKey('links', $result);
        $this->assertArrayHasKey('self', $result['links']);
        $this->assertEquals('/gdc/projects/' . getenv('WRGD_PID'), $result['links']['self']);
    }

    /*public function testExecute() : void
    {
        $migration = new KeboolaGoodDataWriterMigration(new Logger(APP_NAME));
        $migration
            ->setOriginComponentId($this->originComponentId)
            ->setDestinationComponentId($this->destinationComponentId);
        $migration->setImageParameters([
            "gooddata_provisioning_url" => "https://x666avoo5e.execute-api.eu-west-1.amazonaws.com/dev",
            "gooddata_url" => "https://secure.gooddata.com",
            "production_token" => "KB_PROD",
            "demo_token" => "KB_DEMO"
        ]);

        $createdConfigurations = $migration->execute();
        $this->assertNotEmpty($createdConfigurations);
        $this->assertGreaterThanOrEqual(1, count($createdConfigurations));

        $originConfig1 = $this->components->getConfiguration($this->originComponentId, $this->configuration['id']);
        $this->assertArrayHasKey('migrationStatus', $originConfig1['configuration']);
        $this->assertEquals('success', $originConfig1['configuration']['migrationStatus']);
        $destConfig1 = $this->components->getConfiguration($this->destinationComponentId, $this->configuration['id']);
        $this->assertNotEmpty($destConfig1);
        $this->assertArrayHasKey('migrationStatus', $originConfig1['configuration']);
    }

    public function tearDown() : void
    {
        parent::tearDown();
        $this->components->deleteConfiguration($this->originComponentId, $this->configuration['id']);
        $this->components->deleteConfiguration($this->destinationComponentId, $this->configuration['id']);
    }*/
}
