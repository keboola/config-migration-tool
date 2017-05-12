<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 30/06/16
 * Time: 16:53
 */

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Monolog\Logger;

class ExGoogleDriveService
{
    /** @var Client */
    private $client;

    /** @var Logger */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->client = new Client([
            'base_uri' => 'https://syrup.keboola.com/ex-google-drive/',
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN')
            ]
        ]);
    }

    public function getConfigs()
    {
        return $this->request('get', 'configs');
    }

    public function getAccount($id)
    {
        $account = $this->request('get', sprintf('account/%s/decrypt', $id));
        return $account;
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
}
