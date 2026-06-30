<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class StatusCodeMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    /**
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string|array $filter, bool $expandWildcards = true)
    {
        $this-> $expandWildcards = $expandWildcards;
        $this->setMatchingValues($filter);
    }

    public function matchesResponse(ResponseInterface $response): bool
    {
        return $this->matchesRegexp((string)$response->getStatusCode());
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardStringToRegexp($value);
    }
}
