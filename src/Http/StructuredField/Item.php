<?php
declare(strict_types=1);

namespace YAWAF\Core\Http\StructuredField;

/// @todo... add types, getter/setters to enforce them
use YAWAF\Core\Http\HeaderFormat;
use YAWAF\Core\Stdlib;

class Item extends Parameter
{
    /** @var Parameter[] */
    public array $parameters;

    public function __construct(HeaderFormat $type, $value, array $parameters = [])
    {
        if (! Stdlib::array_of($parameters, Parameter::class)) {
            throw new \InvalidArgumentException('parameters argument to Item constructor must be an array of Parameter objects');
        }
        parent::__construct($type, $value);
        $this->parameters = $parameters;
    }

    public function __toString(): string
    {
        $out = parent::__toString();
        if ($this->parameters) {
            $pieces = [];
            foreach ($this->parameters as $name => $parameter)
            {
                if ($parameter->type === HeaderFormat::SFBoolean && $parameter->value) {
                    $pieces[] = $name;
                } else {
                    $pieces[] = $name . '=' . $parameter->__toString();
                }
            }
            $out .= ';' . implode(';', $pieces);
        }
        return $out;
    }
}
