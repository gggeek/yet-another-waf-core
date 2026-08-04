<?php
declare(strict_types=1);

namespace YAWAF\Core\Http\StructuredField;

use YAWAF\Core\Http\HeaderFormat;

interface Parameter
{
    public function getType(): HeaderFormat;

    public function getValue(): mixed;
}
