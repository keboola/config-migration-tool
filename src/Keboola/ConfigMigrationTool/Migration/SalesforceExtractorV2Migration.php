<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;

class SalesforceExtractorV2Migration extends GenericCopyMigrationWithRows
{
    public function transformConfiguration(Configuration $configuration, array $rowConfigurations): array
    {
        //rows
        $newRows = [];
        if (!empty($rowConfigurations)) {
            foreach ($rowConfigurations as $r) {
                $newRows[] = $this->transformRow($r);
            }
        }

        // convert authentication section
        $configurationData = $configuration->getConfiguration();
        //cleanup
        if (isset($configurationData["migrationStatus"])) {
            unset($configurationData["migrationStatus"]);
        }

        if (isset($configurationData["parameters"]["loginname"])) {
            $configurationData["parameters"]["username"] = $configurationData["parameters"]["loginname"];
            unset($configurationData["parameters"]["loginname"]);
        }
        if (isset($configurationData["parameters"]["#securitytoken"])) {
            $configurationData["parameters"]["#security_token"] = $configurationData["parameters"]["#securitytoken"];
            unset($configurationData["parameters"]["#securitytoken"]);
        }
        // default api version
        $configurationData["parameters"]["api_version"] = "39.0";

        $configuration->setConfiguration($configurationData);
        return ["configuration" => $configuration, "rows" => $newRows];
    }

    public function transformRow(ConfigurationRow $row): ConfigurationRow
    {
        // convert custom soql
        $configuration = $row->getConfiguration();
        // default value
        $configuration['parameters']["is_deleted"] = false;

        $configId = $row->getComponentConfiguration()->getConfigurationId();
        $configuration['parameters']["bucket_name"] = "htns-ex-salesforce-" . $configId;

        if (isset($configuration['parameters']['objects'])) {
            if (sizeof($configuration['parameters']['objects']) >= 1) {
                $configuration = $this->convertSoql($configuration);
                $configuration = $this->convertIncremental($configuration);
            }
            unset($configuration['parameters']['objects']);
        }
        $row->setConfiguration($configuration);

        $row = $this->convertState($row);
        return $row;
    }

    protected function convertSoql(array $row): array
    {
        $object = $row['parameters']['objects'][0];
        if (isset($object['soql']) and $object['soql'] != '') {
            $row['parameters']['soql_query'] = $object['soql'];
            $row['parameters']['query_type_selector'] = "Custom SOQL";
        } else {
            $row['parameters']['query_type_selector'] = "Object";
        }

        if (isset($object['name']) && (!isset($object['soql']) or $object['soql'] == '')) {
            $row['parameters']['object'] = $object['name'];
        }
        return $row;
    }

    protected function convertIncremental(array $row): array
    {
        // set default
        $row['parameters']['loading_options'] = [];
        $row['parameters']['loading_options']['pkey'] = [];
        $row['parameters']['loading_options']['incremental'] = 0;

        if (isset($row['parameters']['sinceLast'])) {
            if ($row['parameters']['sinceLast'] == true) {
                $row['parameters']['loading_options']['incremental'] = 1;
                $row['parameters']['loading_options']['pkey'] = ['Id'];
                $row['parameters']['loading_options']['incremental_fetch'] = true;
                $row['parameters']['loading_options']['incremental_field'] = "LastModifiedDate";
            }
            unset($row['parameters']['sinceLast']);
        }
        return $row;
    }

    protected function convertState(ConfigurationRow $row): ConfigurationRow
    {
        // should be set by now
        $rowConfig = $row->getConfiguration();
        if (isset($rowConfig['parameters']['object'])) {
            $name = $rowConfig['parameters']['object'];
            $oldState = $row->getState();
            if (isset($oldState['component']['bulkRequests'][$name])) {
                $epochMilis = $oldState['component']['bulkRequests'][$name];
                $oldState['component']['last_run'] = strftime("%Y-%m-%dT%H:%M:%S.000Z", intval($epochMilis / 1000));
                $oldState['component']['prev_output_columns'] = [];
                unset($oldState['component']['bulkRequests']);
                $row->setState($oldState);
            }
        }
        return $row;
    }

    protected function saveConfigurationOptions(Configuration $configuration, array $options): void
    {
        $c = $this->updateConfigurationOptions($configuration, $options);
        $components = new Components($this->storageApiService->getClient());
        $components->updateConfiguration($c);
    }
}
