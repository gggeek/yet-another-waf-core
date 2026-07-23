<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Message;

use Psr\Http\Message\MessageInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class HeaderMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    protected string $headerName;
    protected bool $headerNameIsRegex = false;

    /**
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string $headerName, string|array $filter, bool $caseInsensitive = false, bool $expandWildcards = true,
        bool $expandWildcardsInName = false)
    {
        $this->caseInsensitive = $caseInsensitive;
        $this->expandWildcards = $expandWildcards;
        $this->headerNameIsRegex = $expandWildcardsInName;

        if ($expandWildcardsInName) {
            $this->headerName = $this->regexpDelimiter . $this->wildcardStringToRegexp($headerName, true) . $this->regexpDelimiter . 'i';
        } else {
/// @todo... give a warning if there are uppercase chars in the header name, as we always match against lower cased names
            $this->headerName = strtolower($headerName);
        }

        $this->setMatchingValues($filter);
    }

    public function matchesMessage(MessageInterface $message): bool
    {
/// @todo... if headerName matches set-cookie, we should probably use different matching logic, as that header has different usage of commans

        if ($this->headerNameIsRegex) {
            foreach ($message->getHeaders() as $headerName => $headerValues) {
                if (preg_match($this->headerName, $headerName)) {
                    foreach ($headerValues as $headerValue) {
/// @todo... depending on the header, values can be concatenated with commas - but also double-quoted!!!
//var_dump($headerValue);
                        if ($this->matchesRegexp($headerValue)) {
                            return true;
                        }
                    }
                }
            }
            return false;
        } else {
            if (!$message->hasHeader($this->headerName)) {
                return false;
            }
            foreach ($message->getHeader($this->headerName) as $headerValue) {
                if ($this->matchesRegexp($headerValue)) {
                    return true;
                }
            }
            return false;
        }
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardStringToRegexp($value);
    }
}
