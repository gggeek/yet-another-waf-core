<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Message;


use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class QueryStringMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    protected string $parameterName;

    /**
     * @param string $parameterName
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string $parameterName, string|array $filter)
    {
        $this->parameterName = $parameterName;
        $this->setMatchingValues($filter);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
/// @todo...
        return false;
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardToRegexp($value);
    }
}
