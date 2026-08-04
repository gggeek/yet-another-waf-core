<?php
declare(strict_types=1);

namespace YAWAF\Core\Http\StructuredField;

/// @todo... add types, getter/setters to enforce them
use YAWAF\Core\Http\HeaderFormat;
use YAWAF\Core\Stdlib;

interface Item extends Parameter
{
    /** @var Parameter[] */
    public function getParameters(): array;
}
