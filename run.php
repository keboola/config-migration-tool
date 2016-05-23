<?php

use Keboola\ConfigMigrationTool\Application;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Monolog\Logger;
use Symfony\Component\Yaml\Yaml;

require_once(dirname(__FILE__) . "/bootstrap.php");

$logger = new Logger(APP_NAME);

try {
    $arguments = getopt("d::", ["data::"]);
    if (!isset($arguments["data"])) {
        throw new UserException('Data folder not set.');
    }
    $config = Yaml::parse(file_get_contents($arguments["data"] . "/config.yml"));
    $app = new Application($logger);

    if (isset($config['action']) && $config['action'] != 'run') {
        echo json_encode($app->action($config));
    } else {
        $app->run($config);
    }
} catch(UserException $e) {
    $logger->log('error', $e->getMessage(), (array) $e->getData());
    exit(1);
} catch(ApplicationException $e) {
    $logger->log('error', $e->getMessage(), (array) $e->getData());
    exit($e->getCode() > 1 ? $e->getCode(): 2);
} catch(\Exception $e) {
    $logger->log('error', $e->getMessage(), [
        'errFile' => $e->getFile(),
        'errLine' => $e->getLine(),
        'trace' => $e->getTrace()
    ]);
    exit(2);
}

exit(0);
