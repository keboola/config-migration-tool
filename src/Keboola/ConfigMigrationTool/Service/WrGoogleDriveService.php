<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;
use Monolog\Logger;
use PhpParser\Node\Arg;

class WrGoogleDriveService
{
    /** @var Client */
    private $client;

    public function __construct($baseUrl = 'https://syrup.keboola.com/wr-google-drive/')
    {
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN'),
            ],
            'handler' => HandlerStack::create(),
        ]);
    }

    public function getConfigs() : array
    {
        return $this->request('get', 'configs');
    }

    public function getAccount(string $id) : array
    {
        return $this->request('get', sprintf('account/%s/decrypt', $id));
    }

    public function getFiles(string $configId, ?string $pageToken = null) : array
    {
        $url = sprintf('files/%s', $configId);
        if ($pageToken !== null) {
            $url .= '/' . $pageToken;
        }

        return $this->request('get', $url);
    }

    public function deleteConfig(string $id) : array
    {
        return $this->request('delete', sprintf('configs/%s', $id));
    }

    public function request(string $method, string $uri, array $options = []) : array
    {
        $response = $this->client->request($method, $uri, $options);
        return \GuzzleHttp\json_decode($response->getBody(), true);
    }

    public function getSheets(string $accountId, string $fileId) : array
    {
        return $this->request('get', sprintf('remote-sheets/%s/%s', $accountId, $fileId));
    }

    public function getRemoteFile(string $accountId, string $fileId) : array
    {
        return $this->request('get', sprintf('remote-file/%s/%s', $accountId, $fileId));
    }
}
