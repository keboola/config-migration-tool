<?php

use Keboola\ConfigMigrationTool\Application;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Monolog\Logger;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

require_once(dirname(__FILE__) . "/bootstrap.php");

$logger = new Logger(APP_NAME, [
    new \Keboola\ConfigMigrationTool\Logger\InfoHandler(),
    new \Monolog\Handler\StreamHandler('php://stderr', Logger::NOTICE)
]);

try {
    $arguments = getopt("d::", ["data::"]);
    if (!isset($arguments["data"])) {
        throw new UserException('Data folder not set.');
    }
    $dataDirectory = $arguments["data"];

    $configFile = "$dataDirectory/config.json";
    if (!file_exists($configFile)) {
        throw new \Exception("Config file not found at path $configFile");
    }
    $jsonDecode = new JsonDecode(true);
    $config = $jsonDecode->decode(file_get_contents($configFile), JsonEncoder::FORMAT);

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
