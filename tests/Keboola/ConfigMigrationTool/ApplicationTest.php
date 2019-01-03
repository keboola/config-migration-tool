<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Application;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Migration\GenericCopyMigration;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    /** @var  Application */
    protected $application;

    protected function setUp() : void
    {
        parent::setUp();
        $this->application = new Application(
            new Logger('test', [new NullHandler()])
        );
    }

    protected function getConfigWithParameters(array $parameters) : array
    {
        return $this->getConfig(['parameters' => $parameters]);
    }

    protected function getConfig(array $config) : array
    {
        return array_merge([
            'image_parameters' => [
                'gooddata_provisioning_url' => 'https://gooddata-provisioning.keboola.com',
                'gooddata_url' => 'https://secure.gooddata.com',
                '#production_token' => 'production',
                '#demo_token' => 'demo',
                'gooddata_writer_url' => 'https://syrup.keboola.com/gooddata-writer',
                '#manage_token' => 'token',
                'project_access_domain' => 'kbc.keboola.com',
            ],
        ], $config);
    }

    public function testApplicationGetMigration() : void
    {
        try {
            $this->application->getMigration($this->getConfigWithParameters(['component' => 'ex-google-analyticsx']));
            $this->fail();
        } catch (UserException $e) {
        }

        $result = $this->application->getMigration($this->getConfigWithParameters([
            'origin' => 'ex-adwords-v2', 'destination' => 'keboola.ex-adwords-v201705',
        ]));
        $this->assertInstanceOf(GenericCopyMigration::class, $result);

        $this->application->getMigration($this->getConfigWithParameters([
            'origin' => 'ex-adwords-v2x', 'destination' => 'keboola.ex-adwords-v201705',
        ]));
        $this->assertInstanceOf(GenericCopyMigration::class, $result);

        $this->application->getMigration($this->getConfigWithParameters([
            'origin' => 'ex-adwords-v2', 'destination' => 'keboola.ex-adwords-v201705x',
        ]));
        $this->assertInstanceOf(GenericCopyMigration::class, $result);
    }

    public function testApplicationGetSupportedMigrations() : void
    {
        $res = $this->application->action($this->getConfig(['action' => 'supported-migrations']));
        $this->assertArrayHasKey('ex-adwords-v2', $res);
        $this->assertCount(1, $res['ex-adwords-v2']);
        $this->assertContains('keboola.ex-adwords-v201705', $res['ex-adwords-v2']);
    }
}
