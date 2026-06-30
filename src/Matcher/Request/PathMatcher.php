<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class PathMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    protected string $prefix;

    /**
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string|array $filter, $prefix='', bool $caseInsensitive = false, bool $expandWildcards = true)
    {
        $this->caseInsensitive = $caseInsensitive;
        $this->expandWildcards = $expandWildcards;
        $this->prefix = $prefix;
        $this->setMatchingValues($filter);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        return $this->matchesRegexp($request->getUri()->getPath());
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardStringToRegexp($this->prefix . $value);
        //return '^' . $this->prefixRegexp . str_replace(['\\*'], ['.*'], preg_quote($value, $this->regexpDelimiter)) . '$';
    }
}
