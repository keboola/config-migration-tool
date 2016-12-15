<?php
/**
 * @copy Keboola 2016
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Configurator;

use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\StorageApi\Options\Components\Configuration;

class ExGoodDataConfigurator
{
    public function create($config)
    {
        $configuration = new Configuration();
        $configuration->setComponentId('keboola.ex-gooddata');
        $configuration->setConfigurationId($config['id']);
        $configuration->setName($config['name']);
        if (!empty($config['description'])) {
            $configuration->setDescription($config['description']);
        }

        return $configuration;
    }


    public function configure($writers, $reports)
    {
        $pid = null;
        foreach ($reports as $report) {
            if (!$pid) {
                $pid = $report['pid'];
            } elseif ($pid != $report['pid']) {
                throw new UserException('Extractor contains reports from different projects. Please ensure that '
                    . 'each configuration contains reports from single project and try the migration again.');
            }
        }

        if (!isset($writers[$pid])) {
            throw new UserException("Writer with GoodData project {$pid} was not found in the project. Please ensure "
                . 'that configurations contain reports from projects configured for your writers only.');
        }

        return [
            'parameters' => [
                'writer_id' => $writers[$pid],
                'reports' => array_column($reports, 'uri')
            ]
        ];
    }
}
