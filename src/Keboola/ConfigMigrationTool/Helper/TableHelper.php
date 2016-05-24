<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 24/05/16
 * Time: 10:27
 */
namespace Keboola\ConfigMigrationTool\Helper;

class TableHelper
{
    public static function formatAttributes($attributes)
    {
        $formatted = [];
        foreach ($attributes as $attribute) {
            $formatted[$attribute['name']] = $attribute['value'];
        }
        return $formatted;
    }
}
