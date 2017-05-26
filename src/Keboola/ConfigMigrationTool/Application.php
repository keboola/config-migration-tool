<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/05/16
 * Time: 11:33
 */

namespace Keboola\ConfigMigrationTool;

use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Migration\GenericCopyMigration;
use Keboola\ConfigMigrationTool\Migration\MigrationInterface;
use Keboola\ConfigMigrationTool\Migration\DockerAppMigration;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class Application
{
    private $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function run($config)
    {
        $migration = $this->getMigration($config);
        $migration->execute();
    }

    public function action($config)
    {
        $action = $config['action'];
        if ($action == 'supported-migrations') {
            return $this->getSupportedMigrations();
        }
        $migration = $this->getMigration($config);
        if (!method_exists($migration, $action)) {
            throw new UserException("Action '$action' does not exist");
        }
        return $migration->$action();
    }

    public function getSupportedMigrations()
    {
        $result = [];
        foreach ($this->getDefinition() as $k => $v) {
            $result[$k] = $v['destinations'];
        }
        return $result;
    }

    public function getDefinition()
    {
        $jsonDecode = new JsonDecode(true);
        return $jsonDecode->decode(file_get_contents(__DIR__ . '/definition.json'), JsonEncoder::FORMAT);
    }

    public function getMigration($config)
    {
        if (isset($config['parameters']['component'])) {
            return $this->getLegacyMigration($config['parameters']['component']);
        } elseif (isset($config['parameters']['origin']) && isset($config['parameters']['destination'])) {
            return $this->getDockerAppMigration($config['parameters']['origin'], $config['parameters']['destination']);
        }
        throw new UserException("Missing parameters 'origin' and 'destination' or 'component'");
    }

    private function getDockerAppMigration($origin, $destination)
    {
        $config = $this->getDefinition();
        if (!isset($config[$origin])) {
            $migration = new GenericCopyMigration($this->logger);
        } elseif (!in_array($destination, $config[$origin]['destinations'])) {
            $migration = new GenericCopyMigration($this->logger);
        } else {
            /** @var DockerAppMigration $migration */
            $migration = $this->getMigrationClass($config[$origin]['migration']);
            if (!($migration instanceof DockerAppMigration)) {
                $class = get_class($migration);
                throw new ApplicationException("Migration class ${$class} is not instance of VersionMigration");
            }
        }
        return $migration->setOriginComponentId($origin)->setDestinationComponentId($destination);
    }

    private function getLegacyMigration($component)
    {
        $componentName = $this->getComponentNameCamelCase($component);
        return $this->getMigrationClass($componentName);
    }

    /**
     * @param $class
     * @return MigrationInterface
     * @throws UserException
     */
    private function getMigrationClass($class)
    {
        /** @var MigrationInterface $migrationClass */
        $migrationClass = sprintf('\\Keboola\\ConfigMigrationTool\\Migration\\%sMigration', $class);
        if (!class_exists($migrationClass)) {
            throw new UserException("Migration for component $class does not exist");
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
