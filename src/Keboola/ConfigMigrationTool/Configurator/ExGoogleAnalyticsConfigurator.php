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

    public function configure($attributes, $data)
    {
        $outputBucket = 'in.c-ex-google-analytics-' . $attributes['id'];
        $configuration = [
            'parameters' => [
                'outputBucket' => $outputBucket,
                'profiles' => [
                    [
                        'id' => $attributes['id']
                    ]
                ]
            ]
        ];

        $oldConfiguration = json_decode($attributes['configuration'], true);
        /**
         *  {
         *      "users":{
         *          "name":"users",
         *          "metrics":["users","sessions"],
         *          "dimensions":["date"],
         *          "filters":""
         *      }
         * }
         */

        $id = 0;
        foreach ($oldConfiguration as $row) {
            $configuration['parameters']['queries'][] = [
                'id' => $id,
                'name' => $row['name'],
                'query' => $this->buildQuery($attributes['id'], $row),
                'outputTable' => $this->getOutputTable($outputBucket, $row['name']),
                'incremental' => true,
                'enabled' => $row['enabled']
            ];
            $id++;
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
            'filtersExpression' => $row['filters'],
            'viewId' => $profileId,
            'dateRanges' => [
                'startDate' => '-4 days',
                'endDate' => '-1 day'
            ],
        ];
    }

    private function getOutputTable($outputBucket, $name)
    {
        return $outputBucket . '.' . $name;
    }
}
