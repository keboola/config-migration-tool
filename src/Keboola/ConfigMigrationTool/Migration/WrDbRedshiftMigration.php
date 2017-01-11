<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 09/01/17
 * Time: 16:02
 */

namespace Keboola\ConfigMigrationTool\Migration;

class WrDbRedshiftMigration extends WrDbMigration
{
    public function __construct($logger)
    {
        parent::__construct($logger, 'mysql');
    }
}
