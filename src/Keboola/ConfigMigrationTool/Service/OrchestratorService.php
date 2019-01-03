<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use GuzzleHttp\Client;
use Keboola\StorageApi\HandlerStack;
use Keboola\StorageApi\Options\Components\Configuration;

class OrchestratorService
{
    /** @var Client */
    private $client;

    public function __construct($baseUrl = 'https://syrup.keboola.com/orchestrator/')
    {
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN'),
            ],
            'handler' => HandlerStack::create(),
        ]);
    }

    /**
     * Get orchestrations from the API in raw form
     * @param string $oldComponentId
     * @return array
     */
    public function listOrchestrations(string $oldComponentId) : array
    {
        $orchestrations = $this->request('get', 'orchestrations');

        return array_filter($orchestrations, function ($orchestration) use ($oldComponentId) {
            $tasks = $this->getTasks((string)$orchestration['id']);
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
     * @param string $oldComponentId
     * @param string $newComponentId
     * @return array
     */
    public function getOrchestrations(string $oldComponentId, string $newComponentId) : array
    {
        $result = [];
        $orchestrations = $this->request('get', 'orchestrations');

        foreach ($orchestrations as $orchestration) {
            $hasOld = false;
            $hasNew = false;
            $tasks = $this->getTasks((string)$orchestration['id']);
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
                    'hasNew' => $hasNew,
                ];
            }
        }

        return $result;
    }

    public function updateOrchestrations(string $oldComponentId, Configuration $newConfiguration) : array
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

    public function updateOrchestration(array $orchestration, string $oldComponentId, Configuration $newConfiguration) : ?array
    {
        $tasks = $this->getTasks((string)$orchestration['id']);

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
            $this->updateTasks((string)$orchestration['id'], $tasks);
            return $orchestration;
        }

        return null;
    }

    public function updateTaskConfig(array $task, string $configurationId) : ?string
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

    public function getTasks(string $orchestrationId) : array
    {
        return $this->request('get', sprintf('orchestrations/%s/tasks', $orchestrationId));
    }

    public function updateTasks(string $orchestrationId, array $tasks) : array
    {
        return $this->request('put', sprintf('orchestrations/%s/tasks', $orchestrationId), [
            'json' => $tasks,
        ]);
    }

    public function request(string $method, string $uri, array $options = []) : array
    {
        $response = $this->client->request($method, $uri, $options);
        $body = (string)$response->getBody();
        return $body ? \GuzzleHttp\json_decode($body, true) : [];
    }
}
