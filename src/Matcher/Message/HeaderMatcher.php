<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Message;


use Psr\Http\Message\MessageInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class HeaderMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    protected string $headerNameRegexp;

    /**
     * @param string|string[] $filter
     * @throws \Exception
     * @todo should we allow disabling separately wildcards for name and for value?
     */
    public function __construct(string $headerName, string|array $filter, bool $caseInsensitive = false, bool $expandWildcards = true)
    {
        $this->caseInsensitive = $caseInsensitive;
        $this->expandWildcards = $expandWildcards;
        $this->headerNameRegexp = $this->regexpDelimiter . $this->wildcardStringToRegexp($headerName) . $this->regexpDelimiter;
        $this->setMatchingValues($filter);
    }

    public function matchesMessage(MessageInterface $message): bool
    {
        /// @todo optimize matching when expandWildcards == false and caseInsensitive == false, avoid this loop by using a non-regexp to match with
        //if (!$message->hasHeader($this->headerName)) {
        //    return false;
        //}
        foreach ($message->getHeaders() as $headerName => $headerValues) {
            if (preg_match($this->headerNameRegexp, $headerName)) {
                return $this->matchesRegexp(implode(', ', $headerValues));
            }
        }
        return false;
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardStringToRegexp($value);
    }
}
