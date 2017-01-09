<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 06/06/16
 * Time: 12:47
 */

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Monolog\Logger;

class WrDbService
{
    private $client;

    private $logger;

    public function __construct($driver, Logger $logger)
    {
        $this->logger = $logger;
        $this->client = new Client([
            'base_uri' => sprintf('https://syrup.keboola.com/wr-db/%s/', $driver),
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN')
            ]
        ]);
    }

    public function getConfigs()
    {
        return $this->request('get', 'configs');
    }

    public function getConfig($id)
    {
        return $this->request('get', 'configs/' . $id);
    }

//    public function getTables($configId)
//    {
//        return $this->request('get', sprintf('%s/tables', $configId));
//    }

    public function getConfigTables($configId)
    {
        return $this->request('get', sprintf('%s/config-tables', $configId));
    }

    public function getCredentials($configId)
    {
        return $this->request('get', sprintf('%s/credentials', $configId));
    }

    public function request($method, $uri, $options = [])
    {
        $response = $this->client->request($method, $uri, $options);
        return \GuzzleHttp\json_decode($response->getBody(),true);
    }
}
