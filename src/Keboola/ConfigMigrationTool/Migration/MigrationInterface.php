<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/05/16
 * Time: 10:47
 */

namespace Keboola\ConfigMigrationTool\Migration;

interface MigrationInterface
{
    public function execute();

    public function status();
}