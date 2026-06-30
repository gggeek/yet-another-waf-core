<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class PathMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    protected string $prefixRegexp;

    /**
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string|array $filter, $prefixRegexp='', bool $caseInsensitive = false, bool $expandWildcards = true)
    {
        $this->caseInsensitive = $caseInsensitive;
        $this->expandWildcards = $expandWildcards;
        $this->prefixRegexp = $prefixRegexp;
        $this->setMatchingValues($filter);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        return $this->matchesRegexp($request->getUri()->getPath());
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardStringToRegexp($this->prefixRegexp . $value);
        //return '^' . $this->prefixRegexp . str_replace(['\\*'], ['.*'], preg_quote($value, $this->regexpDelimiter)) . '$';
    }
}
