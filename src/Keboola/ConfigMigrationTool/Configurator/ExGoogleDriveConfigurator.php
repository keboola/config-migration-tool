<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Configurator;

use Keboola\StorageApi\Options\Components\Configuration;

class ExGoogleDriveConfigurator
{
    public function create(array $account) : Configuration
    {
        $configuration = new Configuration();
        $configuration->setComponentId($this->getComponentId());
        $configuration->setConfigurationId($account['id']);
        $configuration->setName($account['accountNamePretty']);
        $configuration->setDescription(empty($account['description']) ? '' : $account['description']);

        return $configuration;
    }

    public function getComponentId() : string
    {
        return 'keboola.ex-google-drive';
    }

    public function getTableAttributeValue(array  $table, string $name) : ?string
    {
        foreach ($table['attributes'] as $attribute) {
            if ($attribute['name'] == $name) {
                return $attribute['value'];
            }
        }

        return null;
    }

    public function configure(array $account) : array
    {
        $outputBucket = 'in.c-ex-google-drive-' . $account['id'];
        if (!empty($account['outputBucket'])) {
            $outputBucket = $account['outputBucket'];
        }

        $configuration = [
            'authorization' => [
                'oauth_api' => ['id' => $account['id']],
            ],
            'parameters' => [
                'outputBucket' => $outputBucket,
            ],
        ];

        // files
        $cnt = 0;
        foreach ($account['items'] as $sheet) {
            $sheetCfg = json_decode($sheet['config'], true);
            $outputTableArr = explode('.', $sheetCfg['db']['table']);
            $outputTableName = array_pop($outputTableArr);
            $outputBucket = $outputTableArr[0] . '.' . $outputTableArr[1];
            $configuration['parameters']['outputBucket'] = $outputBucket;

            $newSheet = [
                'id' => $cnt++,
                'fileId' => $sheet['googleId'],
                'fileTitle' => $sheet['title'],
                'sheetId' => $sheet['sheetId'],
                'sheetTitle' => $sheet['sheetTitle'],
                'outputTable' => $outputTableName,
                'header' => ['rows' => $sheetCfg['header']['rows']],
                'enabled' => true,
            ];

            if (isset($sheetCfg['header']['sanitize'])) {
                $newSheet['header']['sanitize'] = boolval($sheetCfg['header']['sanitize']);
            }

            $configuration['parameters']['sheets'][] = $newSheet;

            if (isset($sheetCfg['header']) || isset($sheetCfg['transformation']['transpose'])) {
                $configuration['processors']['after'][] = $this->configureProcessors($sheet, $sheetCfg);
            }
        }

        return $configuration;
    }

    private function configureProcessors(array $sheet, array $config) : array
    {
        $processorConfig['definition'] = ['component' => 'keboola.processor.transpose'];
        $parameters = [
            'filename' => $sheet['googleId'] . "_" . $sheet['sheetId'] . ".csv",
            'header_rows_count' => 1,
            'header_sanitize' => true,
        ];

        if (isset($config['transform']['transpose'])
            || isset($config['header']['columns'])
            || (isset($config['header']['rows']) && $config['header']['rows'] > 1)
        ) {
            $parameters['transpose'] = true;
        }
        if (isset($config['header']['sanitize'])) {
            $parameters['header_sanitize'] = boolval($config['header']['sanitize']);
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
