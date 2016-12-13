<?php
/**
 * @copy Keboola 2016
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ExGoodDataService
{
    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://syrup.keboola.com/ex-gooddata/',
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN')
            ],
            'handler' => $this->initRetryHandler()
        ]);
    }

    public function getConfigs()
    {
        return $this->request('get', 'configs');
    }

    public function getReports($configId)
    {
        return $this->request('get', sprintf('reports/%s', $configId));
    }

    public function request($method, $uri, $options = [])
    {
        $response = $this->client->request($method, $uri, $options);
        return \GuzzleHttp\json_decode($response->getBody(),true);
    }

    public function getProjectsWriters()
    {
        $client = new Client([
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN')
            ],
            'handler' => $this->initRetryHandler()
        ]);
        $response = $client->request('get', 'https://syrup.keboola.com/gooddata-writer/v2?include=project');
        $responseParsed = \GuzzleHttp\json_decode($response->getBody(),true);
        $out = [];
        foreach ($responseParsed as $writer) {
            if (isset($writer['project']['pid'])) {
                $out[$writer['project']['pid']] = $writer['id'];
            }
        }
        return $out;
    }

    protected function initRetryHandler()
    {
        $handlerStack = HandlerStack::create();
        /** @noinspection PhpUnusedParameterInspection */
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                return $response && $response->getStatusCode() == 503;
            },
            function ($retries) {
                return rand(60, 600) * 1000;
            }
        ));
        /** @noinspection PhpUnusedParameterInspection */
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                if ($retries >= 5) {
                    return false;
                } elseif ($response && $response->getStatusCode() > 499) {
                    return true;
                } elseif ($error) {
                    return true;
                } else {
                    return false;
                }
            },
            function ($retries) {
                return (int) pow(2, $retries - 1) * 1000;
            }
        ));

        return $handlerStack;
    }
}
