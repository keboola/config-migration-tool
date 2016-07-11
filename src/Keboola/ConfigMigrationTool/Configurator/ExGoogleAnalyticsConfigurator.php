<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 23/05/16
 * Time: 12:47
 */

namespace Keboola\ConfigMigrationTool\Configurator;

use Keboola\StorageApi\Options\Components\Configuration;

class ExGoogleAnalyticsConfigurator
{
    public function create($attributes)
    {
        $configuration = new Configuration();
        $configuration->setComponentId($this->getComponentId());
        $configuration->setConfigurationId($attributes['id']);
        $configuration->setName($attributes['accountName']);
        $configuration->setDescription($attributes['description']);

        return $configuration;
    }

    public function getComponentId()
    {
        return 'keboola.ex-google-analytics-v4';
    }

    public function getTableAttributeValue($table, $name)
    {
        foreach ($table['attributes'] as $attribute) {
            if ($attribute['name'] == $name) {
                return $attribute['value'];
            }
        }

        return null;
    }

    public function configure($account)
    {
        $outputBucket = 'in.c-ex-google-analytics-' . $account['id'];
        $configuration = [
            'authorization' => [
                'oauth_api' => ['id' => $account['id']]
            ],
            'parameters' => [
                'outputBucket' => $outputBucket
            ]
        ];

        // queries
        $oldConfiguration = $account['configuration'];

        $id = 0;
        foreach ($oldConfiguration as $name => $row) {
            $configuration['parameters']['queries'][] = [
                'id' => $id,
                'name' => $name,
                'query' => $this->buildQuery($account['id'], $row),
                'outputTable' => $name,
                'incremental' => true,
                'enabled' => true
            ];
            $id++;
        }

        // profiles
        foreach ($account['items'] as $profile) {
            $configuration['parameters']['profiles'][] = [
                'id' => $profile['googleId'],
                'accountId' => $profile['accountId'],
                'accountName' => $profile['accountName'],
                'name' => $profile['name'],
                'webPropertyId' => $profile['webPropertyId'],
                'webPropertyName' => $profile['webPropertyName']
            ];
        }

        return $configuration;
    }

    private function buildQuery($profileId, $row)
    {
        return [
            'metrics' => array_map(function ($item) {
                    return ['expression' => $item];
                }, $row['metrics']),
            'dimensions' => array_map(function ($item) {
                return ['name' => $item];
            }, $row['dimensions']),
            'filtersExpression' => empty($row['filters'])?[]:$row['filters'],
            'segments' => empty($row['segment'])?[]:[['segmentId' => $row['segment']]],
            'viewId' => $profileId,
            'dateRanges' => [[
                'startDate' => '-4 days',
                'endDate' => '-1 day'
            ]],
        ];
    }

    private function getOutputTable($outputBucket, $name)
    {
        return $outputBucket . '.' . $name;
    }
}
