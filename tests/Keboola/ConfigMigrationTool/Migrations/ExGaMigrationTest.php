<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 23/05/16
 * Time: 10:36
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Configurator\ExGaConfigurator;
use Keboola\ConfigMigrationTool\Migration\ExGaMigration;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Monolog\Logger;
use Symfony\Component\Yaml\Yaml;

class ExGaMigrationTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $config = $this->getConfig();

        $sapiClient = new Client(['token' => getenv('KBC_TOKEN')]);
        $exDbConfiguration = new ExGaConfigurator();
        $components = new Components($sapiClient);

        $oldConfigs = [];

        $tables = $sapiClient->listTables('sys.c-ex-google-analytics');
        foreach ($tables as $table) {
            //@todo
        }

        //@todo
//        $migration = new ExGaMigration();
//        $createdConfigurations = $migration->execute($config);
    }

    private function findConfigurationByName($configurations, $name)
    {
        foreach ($configurations as $configuration) {
            if ($configuration['name'] == $name) {
                return $configuration;
            }
        }

        return false;
    }

    private function getConfig()
    {
        $config = Yaml::parse(file_get_contents(__DIR__ . '/../../../data/ex-ga/config.yml'));
        return $config;
    }
}
