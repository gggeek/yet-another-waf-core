<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher;

interface MatcherInterface
{
    public function matches(...$items): bool;
}
