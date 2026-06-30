<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class QueryStringMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    protected string $parameterNameRegexp;

    /**
     * @todo should we allow disabling separately wildcards for name and for value?
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string $parameterName, string|array $filter, bool $caseInsensitive = false, bool $expandWildcards = true)
    {
        $this->caseInsensitive = $caseInsensitive;
        $this->expandWildcards = $expandWildcards;
        $this->parameterNameRegexp = $this->regexpDelimiter . $this->wildcardStringToRegexp($parameterName) . $this->regexpDelimiter;
        $this->setMatchingValues($filter);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        $qs = $request->getUri()->getQuery();
        parse_str($qs, $pieces);
        /// @todo optimize matching when expandWildcards == false and caseInsensitive == false, avoid this loop by using a non-regexp to match with
        foreach ($pieces as $name => $value) {
            if (preg_match($this->parameterNameRegexp, $name)) {
                return $this->matchesRegexp($value);
            }
        }
        return false;
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardStringToRegexp($value);
    }
}
