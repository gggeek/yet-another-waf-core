<?php
declare(strict_types=1);

namespace YAWAF\Core;

class Stdlib
{
    public static function array_of(array $array, string $className): bool
    {
        foreach ($array as $item) {
            if (!$item instanceof $className) {
                return false;
            }
        }
        return true;
    }

    public static function array_of_array(array $array): bool
    {
        foreach ($array as $item) {
            if (!is_array($item)) {
                return false;
            }
        }
        return true;
    }
}
