<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 06/06/16
 * Time: 12:47
 */

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;
use Monolog\Logger;

class ExDbService
{
    private $client;

    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->client = new Client([
            'base_uri' => 'https://syrup.keboola.com/ex-db/',
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN')
            ],
            'handler' => HandlerStack::create()
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

    public function getQueries($configId)
    {
        return $this->request('get', sprintf('configs/%s/queries', $configId));
    }

    public function getCredentials($configId)
    {
        return $this->request('get', sprintf('configs/%s/credentials', $configId));
    }

    public function request($method, $uri, $options = [])
    {
        $response = $this->client->request($method, $uri, $options);
        return \GuzzleHttp\json_decode($response->getBody(), true);
    }
}
