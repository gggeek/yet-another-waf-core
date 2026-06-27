<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;
use YAWAF\Core\Psr7\ServerRequest\Attributes;

class ClientPortMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    /**
     * @param string|int|string[]|int[] $filter
     * @throws \Exception
     */
    public function __construct(string|int|array $filter)
    {
        if (is_int($filter)) {
            $filter = (string)$filter;
        }
/// @todo... cast ints to strings when an array is received
        $this->setMatchingValues($filter);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        /// @todo... log a warning if we are not passed this env var
        $clientPort = getAttribute(Attributes::class)->get(Attributes::REMOTE_PORT) ?? '';

        return $this->matchesRegexp($clientPort);
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardToRegexp($value);
    }
}
