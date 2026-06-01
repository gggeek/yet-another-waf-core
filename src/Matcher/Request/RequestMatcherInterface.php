<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\MatcherInterface;

/// @todo check: can we avoid making this extend MatcherInterface?
interface RequestMatcherInterface extends MatcherInterface
{
    function matchesRequest(ServerRequestInterface $request): bool;
}
