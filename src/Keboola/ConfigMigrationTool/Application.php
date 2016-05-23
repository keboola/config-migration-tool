<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/05/16
 * Time: 11:33
 */

namespace Keboola\ConfigMigrationTool;

use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Migrations\MigrationInterface;

class Application
{
    private $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function run($config)
    {
        $migration = $this->getMigration($config['component']);
        $migration->execute($config);
    }

    public function action($config)
    {
        $action = $config['action'];
        $migration = $this->getMigration($config['component']);
        if (!method_exists($migration, $action)) {
            throw new UserException(sprintf("Action %s doesn't exist", $action));
        }
        return $migration->$action($config);
    }

    /**
     * @param $component
     * @return MigrationInterface
     */
    private function getMigration($component)
    {
        $componentNameArr = explode('-', $component);

        /** @var MigrationInterface $migrationClass */
        $migrationClass = sprintf(
            '\\Keboola\\ConfigMigrationTool\\Migrations\\%s%sMigration',
            ucfirst($componentNameArr[0]),
            ucfirst($componentNameArr[1])
        );

        return new $migrationClass($this->logger);
    }
}
