<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

class KeboolaGoogleBigQueryWriterMigration extends GenericCopyMigration
{
    public function execute(): array
    {
        return $this->doExecute();
    }

    public function transformConfiguration(array $oldConfig): array
    {
        $newConfig = $oldConfig;
        if (isset($newConfig["configuration"]["authorization"])) {
            unset($newConfig["configuration"]["authorization"]);
        }
        if (isset($newConfig["configuration"]["parameters"]["project"])) {
            unset($newConfig["configuration"]["parameters"]["project"]);
        }
        return $newConfig;
    }
}
