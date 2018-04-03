<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Helper;

class TableHelper
{
    public static function formatAttributes(array $attributes) : array
    {
        $formatted = [];
        foreach ($attributes as $attribute) {
            $formatted[$attribute['name']] = $attribute['value'];
        }
        return $formatted;
    }
}
