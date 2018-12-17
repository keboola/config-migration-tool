<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool;

use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Migration\GenericCopyMigration;
use Keboola\ConfigMigrationTool\Migration\MigrationInterface;
use Keboola\ConfigMigrationTool\Migration\DockerAppMigration;
use Keboola\ConfigMigrationTool\Migration\OAuthMigration;
use Monolog\Logger;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class Application
{
    /** @var Logger */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function run(array $config) : void
    {
        $migration = $this->getMigration($config);
        $migration->execute();
    }

    public function action(array $config) : array
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

    public function getSupportedMigrations() : array
    {
        $result = [];
        foreach ($this->getDefinition() as $k => $v) {
            $result[$k] = $v['destinations'];
        }
        return $result;
    }

    public function getDefinition() : array
    {
        $jsonDecode = new JsonDecode([
            JsonDecode::ASSOCIATIVE => true,
        ]);
        return $jsonDecode->decode(file_get_contents(__DIR__ . '/definition.json'), JsonEncoder::FORMAT);
    }

    public function getMigration(array $config) : MigrationInterface
    {
        if (isset($config['parameters']['component'])) {
            return $this->getLegacyMigration($config['parameters']['component']);
        } elseif (isset($config['parameters']['origin']) && isset($config['parameters']['destination'])) {
            return $this->getDockerAppMigration($config);
        } elseif (isset($config['parameters']['oauth'])) {
            return $this->getOauthMigration($config['parameters']['oauth']);
        }
        throw new UserException("Missing parameters 'origin' and 'destination' or 'component'");
    }

    private function getDockerAppMigration(array $config) : MigrationInterface
    {
        $origin = $config['parameters']['origin'];
        $destination = $config['parameters']['destination'];
        $def = $this->getDefinition();

        if (!isset($def[$origin])) {
            $migration = new GenericCopyMigration($this->logger);
        } elseif (!in_array($destination, $def[$origin]['destinations'])) {
            $migration = new GenericCopyMigration($this->logger);
        } else {
            /** @var DockerAppMigration $migration */
            $migration = $this->getMigrationClass($def[$origin]['migration']);
            if (!($migration instanceof DockerAppMigration)) {
                $class = get_class($migration);
                throw new ApplicationException("Migration class ${$class} is not instance of VersionMigration");
            }
        }
        if (isset($config['image_parameters'])) {
            $migration->setImageParameters($config['image_parameters']);
        }
        return $migration->setOriginComponentId($origin)->setDestinationComponentId($destination);
    }

    private function getOauthMigration(array $oauthConfig) : MigrationInterface
    {
        return new OAuthMigration($oauthConfig, $this->logger);
    }

    private function getLegacyMigration(string $component) : MigrationInterface
    {
        $componentName = $this->getComponentNameCamelCase($component);
        return $this->getMigrationClass($componentName);
    }

    private function getMigrationClass(string $class) : MigrationInterface
    {
        $migrationClass = sprintf('\\Keboola\\ConfigMigrationTool\\Migration\\%sMigration', $class);
        if (!class_exists($migrationClass)) {
            throw new UserException("Migration for component $class does not exist");
        }
        /** @var MigrationInterface $migrationClass */
        return new $migrationClass($this->logger);
    }

    private function getComponentNameCamelCase(string $component) : string
    {
        $componentNameArr = explode('-', $component);
        $componentName = '';
        foreach ($componentNameArr as $c) {
            $componentName .= ucfirst($c);
        }

        return $componentName;
    }
}
