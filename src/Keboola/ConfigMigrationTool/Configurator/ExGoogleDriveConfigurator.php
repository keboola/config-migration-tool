<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 23/05/16
 * Time: 12:47
 */

namespace Keboola\ConfigMigrationTool\Configurator;

use Keboola\StorageApi\Options\Components\Configuration;

class ExGoogleDriveConfigurator
{
    public function create($account)
    {
        $configuration = new Configuration();
        $configuration->setComponentId($this->getComponentId());
        $configuration->setConfigurationId($account['id']);
        $configuration->setName($account['accountNamePretty']);
        $configuration->setDescription($account['description']);

        return $configuration;
    }

    public function getComponentId()
    {
        return 'keboola.ex-google-drive';
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
        $outputBucket = 'in.c-ex-google-drive-' . $account['id'];
        if (!empty($account['outputBucket'])) {
            $outputBucket = $account['outputBucket'];
        }

        $configuration = [
            'authorization' => [
                'oauth_api' => ['id' => $account['id']]
            ],
            'parameters' => [
                'outputBucket' => $outputBucket
            ]
        ];

        // files
        $cnt = 0;
        foreach ($account['items'] as $sheet) {
            $sheetCfg = json_decode($sheet['config'], true);
            $configuration['parameters']['sheets'][] = [
                'id' => $cnt++,
                'fileId' => $sheet['googleId'],
                'fileTitle' => $sheet['title'],
                'sheetId' => $sheet['sheetId'],
                'sheetTitle' => $sheet['sheetTitle'],
                'outputTable' => $sheetCfg['db']['table'],
                'header' => ['rows' => $sheetCfg['header']['rows']],
                'enabled' => true
            ];

            if (isset($sheetCfg['header']) || isset($sheetCfg['transformation']['transpose'])) {
                $configuration['processors']['after'][] = $this->configureProcessors($sheet, $sheetCfg);
            }
        }

        return $configuration;
    }

    private function configureProcessors($sheet, $config)
    {
        $processorConfig['definition'] = ['component' => 'keboola.processor.transpose'];
        $parameters = [
            'filename' => $sheet['googleId'] . "_" . $sheet['sheetId'] . ".csv",
            'header_rows_count' => 1,
            'header_sanitize' => true
        ];

        if (isset($config['header']['sanitize'])) {
            $parameters['header_sanitize'] = $config['header']['sanitize'];
        }
        if (isset($config['header']['rows'])) {
            $parameters['header_rows_count'] = $config['header']['rows'];
        }
        if (isset($config['header']['columns'])) {
            $parameters['header_column_names'] = $config['header']['columns'];
        }
        if (isset($config['header']['transpose']['row'])) {
            $parameters['header_transpose_row'] = $config['header']['transpose']['row'];
        }
        if (isset($config['header']['transpose']['name'])) {
            $parameters['header_transpose_column_name'] = $config['header']['transpose']['name'];
        }
        if (isset($config['transform']['transpose']['from'])) {
            $parameters['transpose_from_column'] = $config['transform']['transpose']['from'];
        }

        $processorConfig['parameters'] = $parameters;

        return $processorConfig;
    }
}
