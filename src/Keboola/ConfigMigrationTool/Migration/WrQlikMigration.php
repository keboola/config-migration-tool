<?php
/**
 * Author: miro@keboola.com
 * Date: 25/07/2017
 */

namespace Keboola\ConfigMigrationTool\Migration;

class WrQlikMigration extends WrDbMigration
{
    public function __construct($logger)
    {
        parent::__construct($logger, 'redshift', 'keboola.wr-qlik');
    }
}
