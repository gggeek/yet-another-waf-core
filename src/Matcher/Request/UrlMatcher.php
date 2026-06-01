<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class UrlMatcher extends BaseMatcher
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
        return $this->matchesRegexp($request->getRequestTarget());
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        $prefix = '';
        // add '/vXX/' prefix support
        if (!preg_match('#^/v[0-9.]+/#', $value)) {
            $prefix = '/v[0-9.]+/';
        }
        // add support for query strings and anchors
        $postfix = '';
        if (!str_contains($value, '?')) {
            $postfix .= '?';
        }
        if (!str_contains($value, '#')) {
            $postfix .= '#';
        }
        if ($postfix !== '') {
            $postfix = "[$postfix].*";
        }

        return '^' . $prefix . str_replace(['*'], ['.*'], preg_quote($value, $this->regexpDelimiter)) . $postfix . '$';
    }
}
