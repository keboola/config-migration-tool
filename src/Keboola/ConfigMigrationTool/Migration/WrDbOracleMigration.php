<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 09/01/17
 * Time: 15:59
 */

namespace Keboola\ConfigMigrationTool\Migration;

class WrDbOracleMigration extends WrDbMigration
{
    public function __construct($logger)
    {
        parent::__construct($logger, 'oracle');
    }
}