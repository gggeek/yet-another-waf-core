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
     * @param string $parameterName
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string $parameterName, string|array $filter)
    {
        $this->parameterNameRegexp = $this->regexpDelimiter . $this->wildcardToRegexp($parameterName) . $this->regexpDelimiter;
        $this->setMatchingValues($filter);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        $qs = $request->getUri()->getQuery();
        parse_str($qs, $pieces);
        foreach ($pieces as $name => $value) {
            if (preg_match($this->parameterNameRegexp, $name)) {
                return $this->matchesRegexp($value);
            }
        }
        return false;
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardToRegexp($value);
    }
}
