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
     * @see https://www.rfc-editor.org/info/rfc9110/#name-status-codes
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string|array $filter, bool $expandWildcards = true)
    {
        $this->expandWildcards = $expandWildcards;
/// @todo... check that the passed in values match either a int string between 100 and 599, or a wildcard pattern
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
