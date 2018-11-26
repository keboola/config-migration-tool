<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\OAuthMigration;
use Keboola\ConfigMigrationTool\Service\OAuthService;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class OAuthMigrationTest extends TestCase
{
    /** @var Client */
    private $storageApiClient;

    /** @var Components */
    private $components;

    /** @var OAuthService */
    private $oauthService;

    /** @var string */
    private $configurationId1;

    /** @var string */
    private $configurationId2;

    /** @var string */
    private $componentId = 'keboola.ex-google-drive';

    public function setUp() : void
    {
        $this->storageApiClient = new Client(['token' => getenv('KBC_TOKEN'), 'url' => getenv('KBC_URL')]);
        $this->components = new Components($this->storageApiClient);
        $this->oauthService = new OAuthService();

        $this->configurationId1 = $this->createTestConfiguration($this->componentId);
        $this->configurationId2 = $this->createTestConfiguration($this->componentId);

        $this->createOAuthConfig($this->configurationId1);
        $this->createOAuthConfig($this->configurationId2);
    }

    protected function createTestConfiguration(string $componentId) : string
    {
        $id = uniqid('migration-test');

        $c = new Configuration();
        $c->setComponentId($componentId);
        $c->setConfigurationId($id);
        $c->setName($id);
        $c->setDescription('Migrate this account');
        $c->setConfiguration(['authorization' => ['oauth_api' => ['id' => $id]]]);
        $this->components->addConfiguration($c);

        return $id;
    }

    protected function createOAuthConfig($configId) : void
    {
        $this->oauthService->createCredentials($this->componentId, [
            'id' => $configId,
            'email' => 'test@keboola.com',
            'accessToken' => '12345',
            'refreshToken' => '567890'
        ]);
    }

    protected function createMigrationConfig($configId) : array
    {
        return [
            'region' => 'us-east-1',
            'componentId' => $this->componentId,
            'id' => $configId
        ];
    }

    public function testExecute()
    {
        $migration = new OAuthMigration(
            $this->createMigrationConfig($this->configurationId1),
            new Logger(APP_NAME)
        );
        $migration->execute();

        $migration = new OAuthMigration(
            $this->createMigrationConfig($this->configurationId2),
            new Logger(APP_NAME)
        );
        $migration->execute();
    }
}
