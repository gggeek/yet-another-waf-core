<?php
declare(strict_types=1);

namespace YAWAF\Core\Http\StructuredField\Parameter;

use YAWAF\Core\Http\HeaderFormat;

class Boolean extends Base
{
    public function __construct(bool $value)
    {
        $this->value = $value;
        $this->type = HeaderFormat::SFBoolean;
    }
}
