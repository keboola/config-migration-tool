<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/05/16
 * Time: 11:33
 */

namespace Keboola\ConfigMigrationTool;

use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Migration\MigrationInterface;

class Application
{
    private $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function run($config)
    {
        $migration = $this->getMigration($config['parameters']['component']);
        $migration->execute();
    }

    public function action($config)
    {
        $action = $config['action'];
        $migration = $this->getMigration($config['parameters']['component']);
        if (!method_exists($migration, $action)) {
            throw new UserException(sprintf("Action %s doesn't exist", $action));
        }
        return $migration->$action();
    }

    /**
     * @param $component
     * @return MigrationInterface
     * @throws UserException
     */
    private function getMigration($component)
    {
        $componentName = $this->getComponentNameCamelCase($component);
        /** @var MigrationInterface $migrationClass */
        $migrationClass = sprintf(
            '\\Keboola\\ConfigMigrationTool\\Migration\\%sMigration',
            $componentName
        );

        if (!class_exists($migrationClass)) {
            throw new UserException("Migration for component $componentName does not exist");
        }

        return new $migrationClass($this->logger);
    }

    private function getComponentNameCamelCase($component)
    {
        $componentNameArr = explode('-', $component);
        $componentName = '';
        foreach ($componentNameArr as $c) {
            $componentName .= ucfirst($c);
        }

        return $componentName;
    }
}
