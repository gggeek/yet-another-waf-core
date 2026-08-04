<?php
declare(strict_types=1);

namespace YAWAF\Core\Http\StructuredField;

/// @todo... add types, getter/setters to enforce them
use YAWAF\Core\Stdlib;

class Item extends Parameter
{
    /** @var Parameter[] */
    public array $parameters;

    public function __construct($type, $value, array $parameters = [])
    {
        if (! Stdlib::array_of($parameters, Parameter::class)) {
            throw new \InvalidArgumentException('parameters argument to Item constructor must be an array of Parameter objects');
        }
        parent::__costruct($type, $value);
        $this->parameters = $parameters;
    }
}
