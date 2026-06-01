<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Response;

use Psr\Http\Message\ResponseInterface;
use YAWAF\Core\Matcher\MatcherInterface;

/// @todo check: can we avoid making this extend MatcherInterface?
interface ResponseMatcherInterface extends MatcherInterface
{
    function matchesResponse(ResponseInterface $response): bool;
}
