<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Logic;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\MatcherInterface;
use YAWAF\Core\Matcher\Request\RequestMatcherInterface;
use YAWAF\Core\Matcher\Response\ResponseMatcherInterface;

class NegativeMatcher implements RequestMatcherInterface, ResponseMatcherInterface
{
    protected MatcherInterface $matcher;

    public function __construct(MatcherInterface $matcher)
    {
        $this->matcher = $matcher;
    }

    public function matches(...$items): bool
    {
        return !$this->matcher->matches(...$items);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        return $this->matches($request);
    }

    public function matchesResponse(ResponseInterface $response): bool
    {
        return $this->matches($response);
    }
}
