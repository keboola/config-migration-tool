<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 26/05/16
 * Time: 10:15
 */
namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;

class OrchestratorService
{
    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://syrup.keboola.com/orchestrator/',
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN')
            ]
        ]);
    }

    public function getAffectedOrchestrations($oldComponentId)
    {
        $affected = [];
        $orchestrations = $this->request('get', 'orchestrations');

        foreach ($orchestrations as $orchestration) {
            $tasks = $this->getTasks($orchestration['id']);
            foreach ($tasks as $task) {
                if ((isset($task['componentUrl']) && (false !== strstr($task['componentUrl'], $oldComponentId)))
                || (isset($task['component']) && ($oldComponentId == $task['component']))) {
                    $affected[] = $orchestration;
                }
            }
        }

        return $affected;
    }

    public function updateOrchestrations($oldComponentId, $newComponentId)
    {
        $orchestrations = $this->request('get', 'orchestrations');

        $updatedOrchestrations = [];
        foreach ($orchestrations as $orchestration) {
            $tasks = $this->getTasks($orchestration['id']);
            foreach ($tasks as $task) {
                if (isset($task['componentUrl']) && (false !== strstr($task['componentUrl'], $oldComponentId))) {
                    $task['componentUrl'] = str_replace(
                        $oldComponentId,
                        $newComponentId,
                        $task['componentUrl']
                    );
                } else if (isset($task['component']) && ($oldComponentId == $task['component'])) {
                    $task['component'] = $newComponentId;
                }
            }
            $this->request('put', sprintf('orchestrations/%s/tasks', $orchestration['id'], [
                'json' => $tasks
            ]));
            $updatedOrchestrations[] = $orchestration;
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
