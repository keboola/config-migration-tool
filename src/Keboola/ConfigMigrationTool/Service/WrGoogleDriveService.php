<?php
/**
 * Author: miro@keboola.com
 * Date: 23/05/2017
 */
namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;
use Monolog\Logger;

class WrGoogleDriveService
{
    /** @var Client */
    private $client;

    /** @var Logger */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->client = new Client([
            'base_uri' => 'https://syrup.keboola.com/wr-google-drive/',
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN')
            ],
            'handler' => HandlerStack::create()
        ]);
    }

    public function createAccoung()
    {
        //@TODO: create account for testing purposes -> the tokens need to be encrypted in configuration
    }

    public function getConfigs()
    {
        return $this->request('get', 'configs');
    }

    public function getAccount($id)
    {
        return $this->request('get', sprintf('account/%s/decrypt', $id));
    }

    public function getFiles($configId, $pageToken = null)
    {
        $url = sprintf('files/%s', $configId);
        if ($pageToken !== null) {
            $url .= '/' . $pageToken;
        }

        return $this->request('get', $url);
    }

    public function deleteConfig($id)
    {
        return $this->request('delete', sprintf('configs/%s', $id));
    }

    public function request($method, $uri, $options = [])
    {
        $response = $this->client->request($method, $uri, $options);
        return \GuzzleHttp\json_decode($response->getBody(), true);
    }

    public function getSheets($accountId, $fileId)
    {
        return $this->request('get', sprintf('remote-sheets/%s/%s', $accountId, $fileId));
    }
}
