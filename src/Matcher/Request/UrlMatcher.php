<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class UrlMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    protected bool $allowQueryString;
    protected bool $allowAnchor;
    protected string $prefixRegexp;

    /**
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string|array $filter, bool $allowQueryString=false, bool $allowAnchor=false, $prefixRegexp='')
    {
        $this->allowQueryString = $allowQueryString;
        $this->allowAnchor = $allowAnchor;
        $this->prefixRegexp = $prefixRegexp;
        $this->setMatchingValues($filter);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        return $this->matchesRegexp($request->getRequestTarget());
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        $postfixRegexp = '';
        if ($this->allowQueryString && !str_contains($value, '?')) {
            $postfixRegexp .= '?';
        }
        if ($this->allowAnchor && !str_contains($value, '#')) {
            $postfixRegexp .= '#';
        }
        if ($postfixRegexp !== '') {
            $postfixRegexp = "[$postfixRegexp].*";
        }

        return '^' . $this->prefixRegexp . str_replace(['*'], ['.*'], preg_quote($value, $this->regexpDelimiter)) . $postfixRegexp . '$';
    }
}
