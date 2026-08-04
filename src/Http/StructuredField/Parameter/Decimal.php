<?php
declare(strict_types=1);

namespace YAWAF\Core\Http\StructuredField\Parameter;

use YAWAF\Core\Http\HeaderFormat;

class Decimal extends Base
{
    public function __construct(float $value)
    {
        $this->value = $value;
        $this->type = HeaderFormat::SFDecimal;
    }
}
