<?php
declare(strict_types=1);

namespace YAWAF\Core\Http\StructuredField;

/// @todo... add types, getter/setters to enforce them
class Parameter
{
    public $type;
    public $value;

    public function __construct($type, $value)
    {
        $this->type = $type;
        $this->value = $value;
    }
}
