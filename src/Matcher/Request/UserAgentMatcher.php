<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class UserAgentMatcher extends BaseMatcher
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
        $env = $request->getServerParams();
        /// @todo... log a warning if we are not passed this env var
        $ua = $env['HTTP_USER_AGENT'] ?? '';

        return $this->matchesRegexp($ua);
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardToRegexp($value);
    }
}
