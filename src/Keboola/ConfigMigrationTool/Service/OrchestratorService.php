<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 26/05/16
 * Time: 10:15
 */
namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;

class OrchestratorService
{
    private $client;

    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->client = new Client([
            'base_uri' => 'https://syrup.keboola.com/orchestrator/',
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN')
            ]
        ]);
    }

    public function getOrchestrations($oldComponentId, $newComponentId)
    {
        $result = [];
        $orchestrations = $this->request('get', 'orchestrations');

        foreach ($orchestrations as $orchestration) {
            $hasOld = false;
            $hasNew = false;
            $tasks = $this->getTasks($orchestration['id']);
            foreach ($tasks as $task) {
                if ((isset($task['componentUrl']) && (false !== strstr($task['componentUrl'], '/' . $oldComponentId . '/')))
                || (isset($task['component']) && ($oldComponentId == $task['component']))) {
                    $hasOld = true;
                }

                if ((isset($task['componentUrl']) && (false !== strstr($task['componentUrl'], '/' . $newComponentId)))
                    || (isset($task['component']) && (false !== strstr($task['component'], $newComponentId)))) {
                    $hasNew = true;
                }
            }

            if ($hasNew || $hasOld) {
                $result[] = [
                    'id' => $orchestration['id'],
                    'name' => $orchestration['name'],
                    'hasOld' => $hasOld,
                    'hasNew' => $hasNew
                ];
            }
        }

        return $result;
    }

    public function updateOrchestrations($oldComponentId, Configuration $newConfiguration)
    {
        $orchestrations = $this->request('get', 'orchestrations');

        $updatedOrchestrations = [];
        foreach ($orchestrations as $orchestration) {
            $tasks = $this->getTasks($orchestration['id']);

            $update = false;
            foreach ($tasks as &$task) {
                if (
                    isset($task['actionParameters']['config'])
                    && ($task['actionParameters']['config'] == $newConfiguration->getConfigurationId())
                ) {
                    if (isset($task['componentUrl']) && (false !== strstr($task['componentUrl'], '/' . $oldComponentId .'/'))) {
                        $task['componentUrl'] = str_replace(
                            $oldComponentId,
                            $newConfiguration->getComponentId(),
                            $task['componentUrl']
                        );
                        $update = true;
                    } else if (isset($task['component']) && ($oldComponentId == $task['component'])) {
                        $task['component'] = $newConfiguration->getComponentId();
                        $update = true;
                    }
                }
            }

            if ($update) {
                $this->request('put', sprintf('orchestrations/%s/tasks', $orchestration['id']), [
                    'json' => $tasks
                ]);
                $updatedOrchestrations[] = $orchestration;
            }
        }

        return $updatedOrchestrations;
    }

    public function getTasks($orchestrationId)
    {
        return $this->request('get', sprintf('orchestrations/%s/tasks', $orchestrationId));
    }

    public function request($method, $uri, $options = [])
    {
        $response = $this->client->request($method, $uri, $options);
        return \GuzzleHttp\json_decode($response->getBody(),true);
    }
}
