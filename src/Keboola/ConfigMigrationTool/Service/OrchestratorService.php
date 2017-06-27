<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 26/05/16
 * Time: 10:15
 */
namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;
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
            ],
            'handler' => HandlerStack::create()
        ]);
    }

    /**
     * Get orchestrations from the API in raw form
     * @param $oldComponentId
     * @return array
     */
    public function listOrchestrations($oldComponentId)
    {
        $orchestrations = $this->request('get', 'orchestrations');

        return array_filter($orchestrations, function ($orchestration) use ($oldComponentId) {
            $tasks = $this->getTasks($orchestration['id']);
            foreach ($tasks as $task) {
                if ((isset($task['componentUrl'])
                    && (false !== strstr($task['componentUrl'], '/' . $oldComponentId . '/')))
                    || (isset($task['component']) && ($oldComponentId == $task['component']))) {
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * Get orchestrations from the API and return them in form for displaying in status
     * @param $oldComponentId
     * @param $newComponentId
     * @return array
     */
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
        $orchestrations = $this->listOrchestrations($oldComponentId);

        $updatedOrchestrations = [];
        foreach ($orchestrations as $orchestration) {
            $updated = $this->updateOrchestration($orchestration, $oldComponentId, $newConfiguration);
            if ($updated !== null) {
                $updatedOrchestrations[] = $updated;
            }
        }

        return $updatedOrchestrations;
    }

    public function updateOrchestration($orchestration, $oldComponentId, Configuration $newConfiguration)
    {
        $tasks = $this->getTasks($orchestration['id']);

        $update = false;
        foreach ($tasks as &$task) {
            $config = $this->updateTaskConfig($task, $newConfiguration->getConfigurationId());

            if ($config !== null) {
                unset($task['actionParameters']['account']);
                $task['actionParameters']['config'] = $config;
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
            $this->updateTasks($orchestration['id'], $tasks);
            return $orchestration;
        }

        return null;
    }

    public function updateTaskConfig($task, $configurationId)
    {
        $config = null;
        if (isset($task['actionParameters']['config'])
            && ($task['actionParameters']['config'] == $configurationId)) {
            $config = $task['actionParameters']['config'];
        } elseif (isset($task['actionParameters']['account'])
            && ($task['actionParameters']['account'] == $configurationId)) {
            $config = $task['actionParameters']['account'];
        }

        return $config;
    }

    public function getTasks($orchestrationId)
    {
        return $this->request('get', sprintf('orchestrations/%s/tasks', $orchestrationId));
    }

    public function updateTasks($orchestrationId, $tasks)
    {
        return $this->request('put', sprintf('orchestrations/%s/tasks', $orchestrationId), [
            'json' => $tasks
        ]);
    }

    public function request($method, $uri, $options = [])
    {
        $response = $this->client->request($method, $uri, $options);
        $body = (string)$response->getBody();
        return $body ? \GuzzleHttp\json_decode($body, true) : null;
    }
}
