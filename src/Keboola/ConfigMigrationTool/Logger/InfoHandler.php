<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 30/05/16
 * Time: 14:18
 */
namespace Keboola\ConfigMigrationTool\Logger;

use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class InfoHandler extends FilterHandler
{
    public function __construct()
    {
        parent::__construct(
            new StreamHandler('php://stdout', Logger::INFO, false),
            [Logger::INFO]
        );
    }
}
