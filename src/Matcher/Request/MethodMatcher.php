<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\StringListMatcherTrait;

class MethodMatcher extends BaseMatcher
{
    use StringListMatcherTrait;

    /**
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string|array $filter)
    {
        if (is_array($filter)) {
            $this->setMatchingStrings(...$filter);
        } else {
            $this->setMatchingStrings($filter);
        }
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        return $this->matchesString($request->getMethod());
    }

    protected function normalizeMatchingString(string $value): string
    {
        return strtoupper(trim($value));
    }
}
