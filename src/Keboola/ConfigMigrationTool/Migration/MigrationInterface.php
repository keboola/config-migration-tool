<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

interface MigrationInterface
{
    public function execute() : array;

    public function status() : array;
}
