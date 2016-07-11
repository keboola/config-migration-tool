<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 30/06/16
 * Time: 16:53
 */

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\Encryption\AesEncryptor;
use Monolog\Logger;

class ExGoogleAnalyticsService
{
    /** @var Client */
    private $client;

    /** @var Logger */
    private $logger;

    /** @var AesEncryptor */
    private $encryptor;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->client = new Client([
            'base_uri' => 'https://syrup.keboola.com/ex-google-analytics/',
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN')
            ]
        ]);
        $this->encryptor = new AesEncryptor(getenv('GOOGLE_KEY'));
    }

    public function getConfigs()
    {
        return $this->request('get', 'configs');
    }

    public function getAccount($id)
    {
        $account = $this->request('get', 'account/' . $id);
        $account['accessToken'] = $this->decrypt($account['accessToken']);
        $account['refreshToken'] = $this->decrypt($account['refreshToken']);
        return $account;
    }

    public function getProfiles($configId)
    {
        return $this->request('get', sprintf('profiles/%s', $configId));
    }

    public function request($method, $uri, $options = [])
    {
        $response = $this->client->request($method, $uri, $options);
        return \GuzzleHttp\json_decode($response->getBody(),true);
    }

    private function decrypt($data)
    {
        return $this->encryptor->decrypt(base64_decode($data));
    }
}
