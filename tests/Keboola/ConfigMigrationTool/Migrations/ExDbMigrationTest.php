<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/05/16
 * Time: 11:10
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Migrations\ExDbMigration;
use Symfony\Component\Yaml\Yaml;

class ExDbMigrationTest extends \PHPUnit_Framework_TestCase
{
    public function testMigration()
    {
        $migration = new ExDbMigration();
        $createdConfigurations = $migration->execute($this->getConfig());
    }

    private function getConfig()
    {
        return Yaml::parse(file_get_contents(__DIR__ . '/../../../data/config.yml'));
    }
}
