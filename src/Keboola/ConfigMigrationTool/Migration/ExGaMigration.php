<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 23/05/16
 * Time: 12:46
 */

namespace Keboola\ConfigMigrationTool\Migration;


use Keboola\ConfigMigrationTool\Configurator\ExGaConfigurator;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;

class ExGaMigration implements MigrationInterface
{

    public function execute()
    {
        $sapiClient = new Client(['token' => getenv('KBC_TOKEN')]);
        $components = new Components($sapiClient);

        $tables = $sapiClient->listTables('sys.c-ex-google-analytics');
        $createdConfigurations = [];
        foreach ($tables as $table) {
            $csvData = $sapiClient->exportTable($table['id']);
            $tableData = $sapiClient::parseCsv($csvData);

            //@todo: migrate tokens to OAuth Bundle

            $configurator = new ExGaConfigurator();
            $response = $components->addConfiguration($configurator->configure($table, $tableData));

            $createdConfigurations[] = $response;
        }

        return $createdConfigurations;
    }

    public function status()
    {
        // TODO: Implement status() method.
    }
}
