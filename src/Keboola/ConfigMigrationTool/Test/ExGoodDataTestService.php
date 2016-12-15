<?php
/**
 * @copy Keboola 2016
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Service\ExGoodDataService;

class ExGoodDataTestService extends ExGoodDataService
{
    private $configs;
    private $projectsWriters;

    public function __construct($configs, $projectsWriters)
    {
        parent::__construct();
        $this->configs = $configs;
        $this->projectsWriters = $projectsWriters;
    }

    public function getProjectsWriters()
    {
        return $this->projectsWriters;
    }

    public function getConfigs()
    {
        return $this->configs;
    }
}