<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\OAuthMigration;
use Keboola\ConfigMigrationTool\Service\OAuthV3Service;
use Keboola\ConfigMigrationTool\Test\OAuthV2Service;
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

    /** @var OAuthV2Service */
    private $oauthService;

    /** @var array */
    private $configurations = [];

    /** @var array */
    private $oauthCredentials = [];

    /** @var string */
    private $componentId = 'keboola.ex-google-drive';

    /** @var OAuthV3Service */
    private $oauthV3Service;

    public function setUp() : void
    {
        $this->storageApiClient = new Client(['token' => getenv('KBC_TOKEN'), 'url' => getenv('KBC_URL')]);
        $this->components = new Components($this->storageApiClient);
        $this->oauthService = new OAuthV2Service('https://syrup.keboola.com/oauth-v2/');
        $this->oauthV3Service = new OAuthV3Service('https://oauth.keboola.com/');

        $this->configurations[] = $this->createTestConfiguration($this->componentId);
        $this->configurations[] = $this->createTestConfiguration($this->componentId);
        $this->configurations[] = $this->createTestConfiguration($this->componentId);
        $this->configurations[] = $this->createTestConfiguration($this->componentId);

        $this->oauthCredentials[] = $this->createCredentials($this->configurations[0]);
        $this->oauthCredentials[] = $this->createCredentials($this->configurations[1]);
        $this->oauthCredentials[] = $this->createCredentialsCustom($this->configurations[2]);
        $this->oauthCredentials[] = $this->createCredentialsCustom($this->configurations[3]);
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

    protected function createCredentials(string $configId) : \stdClass
    {
        return $this->oauthService->createCredentials($this->componentId, [
            'id' => $configId,
            'email' => 'test@keboola.com',
            'accessToken' => '12345',
            'refreshToken' => '567890',
        ]);
    }

    protected function createCredentialsCustom(string $configId): \stdClass
    {
        return $this->oauthService->createCredentials($this->componentId, [
            'id' => $configId,
            'email' => 'test@keboola.com',
            'accessToken' => '12345',
            'refreshToken' => '567890',
            'appKey' => 'appKey.12345.google.com',
            'appSecretDocker' => 'KBC::ComponentSecure::tajmostvo',
            'data' => [
                'access_token' => 'qwertyuiop1234567890',
            ],
        ]);
    }

    protected function createMigrationConfig() : array
    {
        return [
            'configurations' => array_map(
                function (string $configId) {
                    return [
                        'componentId' => $this->componentId,
                        'id' => $configId,
                    ];
                },
                $this->configurations
            ),
        ];
    }

    public function testExecute() : void
    {
        $this->assertCount(4, $this->configurations);

        $migration = new OAuthMigration(
            $this->createMigrationConfig(),
            new Logger(APP_NAME)
        );
        $responses = $migration->execute();

        $this->assertCount(4, $responses);

        foreach ($responses as $index => $response) {
            $this->assertArrayHasKey('id', $response);
            $this->assertArrayHasKey('authorizedFor', $response);
            $this->assertArrayHasKey('creator', $response);
            $this->assertArrayHasKey('id', $response['creator']);
            $this->assertArrayHasKey('description', $response['creator']);
            $this->assertArrayHasKey('created', $response);
            $this->assertArrayHasKey('#data', $response);
            $this->assertArrayHasKey('oauthVersion', $response);
            $this->assertArrayHasKey('appKey', $response);
            $this->assertArrayHasKey('#appSecret', $response);

            $this->assertEquals('test@keboola.com', $response['authorizedFor']);
            $this->assertContains($response['id'], $this->configurations);

            $oldCredentials = $this->oauthCredentials[$index];
            $this->assertEquals($oldCredentials->{'#data'}, $response['#data']);

            if ($index > 1) {
                $this->assertEquals('KBC::ComponentSecure::tajmostvo', $response['#appSecret']);
            }
        }

        //cleanup
        foreach ($responses as $res) {
            $this->oauthV3Service->deleteCredentials($this->componentId, $res['id']);
        }
    }
}