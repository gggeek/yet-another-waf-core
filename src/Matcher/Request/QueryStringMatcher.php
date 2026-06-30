<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class QueryStringMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    protected string $parameterName;
    protected bool $parameterNameIsRegex = false;

    /**
     * @todo allow wildcards $parameterName, while allowing disabling separately wildcards for name and for value
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string $parameterName, string|array $filter, bool $caseInsensitive = false, bool $expandWildcards = true)
    {
        $this->caseInsensitive = $caseInsensitive;
        $this->expandWildcards = $expandWildcards;
        if ($this->parameterNameIsRegex) {
            $this->parameterName = $this->regexpDelimiter . $this->wildcardStringToRegexp($parameterName) . $this->regexpDelimiter;
        } else {
            $this->parameterName = $parameterName;
        }

        $this->setMatchingValues($filter);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        $pieces = $request->getQueryParams();
        //$qs = $request->getUri()->getQuery();
        //parse_str($qs, $pieces);
        if ($this->parameterNameIsRegex) {
            foreach ($pieces as $name => $value) {
                if (preg_match($this->parameterName, $name)) {
                    return $this->matchesRegexp($value);
                }
            }
            return false;
        } else {
            if (!array_key_exists($this->parameterName, $pieces)) {
                return false;
            }
            return $this->matchesRegexp($pieces[$this->parameterName]);
        }
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardStringToRegexp($value);
    }
}
