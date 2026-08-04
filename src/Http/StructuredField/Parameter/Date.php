<?php
declare(strict_types=1);

namespace YAWAF\Core\Http\StructuredField\Parameter;

use YAWAF\Core\Http\HeaderFormat;

class Date extends Base
{
    public function __construct(int $value)
    {
        $this->value = $value;
        $this->type = HeaderFormat::SFDate;
    }
}
