<?php
declare(strict_types=1);

namespace YAWAF\Core\Http\StructuredField\Item;

use YAWAF\Core\Http\StructuredField\Item;
use YAWAF\Core\Http\StructuredField\ItemTrait;
use YAWAF\Core\Http\StructuredField\Parameter\Boolean as Base;

class Boolean extends Base implements Item
{
    use ItemTrait;

    public function __construct(bool $value, array $parameters = [])
    {
        parent::__construct($value);
        $this->setParameters($parameters);
    }
}
