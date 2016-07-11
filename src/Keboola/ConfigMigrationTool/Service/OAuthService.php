<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 31/05/16
 * Time: 17:25
 */

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;

class OAuthService
{
    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://syrup.keboola.com/oauth-v2/',
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN'),
//                'Content-Type' => 'application/json'
            ]
        ]);
    }

    public function getCredentials($componentId, $id)
    {
        $response = $this->client->get(sprintf('credentials/%s/%s', $componentId, $id));

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }

    public function createCredentials($componentId, $account)
    {
        $response = $this->client->post(sprintf('credentials/%s', $componentId), [
            'body' => \GuzzleHttp\json_encode([
                "id" => $account['id'],
                "authorizedFor" => $account['email'],
                "data" => [
                    "access_token" => $account['accessToken'],
                    "refresh_token" => $account['refreshToken']
                ]
            ])
        ]);

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }

}
