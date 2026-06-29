<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class HostMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    /**
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string|array $filter)
    {
        $this->setMatchingValues($filter);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        $host = explode(':', $request->getHeaderLine('Host'), 2)[0];
        return $this->matchesRegexp($host);
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardToRegexp($value);
    }
}
