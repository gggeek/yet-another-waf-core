<?php
declare(strict_types=1);

namespace YAWAF\Core\Http\StructuredField\Parameter;

use YAWAF\Core\Http\HeaderFormat;

class Token extends Base
{
    public function __construct(string $value)
    {
        $this->value = $value;
        $this->type = HeaderFormat::SFToken;
    }
}
