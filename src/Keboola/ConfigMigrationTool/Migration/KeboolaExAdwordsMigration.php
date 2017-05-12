<?php
/**
 * @copy Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\ConfigMigrationTool\Migration;

use Monolog\Logger;

class KeboolaExAdwordsMigration implements MigrationInterface
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function execute()
    {
        //@TODO
    }

    public function status()
    {
        //@TODO
    }
}
