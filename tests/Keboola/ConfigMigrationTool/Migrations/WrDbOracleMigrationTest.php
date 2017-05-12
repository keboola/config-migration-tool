<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/01/17
 * Time: 14:15
 */

namespace Keboola\ConfigMigrationTool\Test\Migrations;

class WrDbOracleMigrationTest extends WrDbMysqlMigrationTest
{
    protected $driver = 'oracle';
}
