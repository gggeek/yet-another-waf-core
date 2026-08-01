<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Message;

use Psr\Http\Message\MessageInterface;
use YAWAF\Core\Http\HeaderParser;
use YAWAF\Core\Http\HeaderParserOnError;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

/**
 * Matches headers whose length (in bytes) is equal or greater than a given value.
 */
class HeaderLengthMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    protected string $headerName;
    protected bool $headerNameIsRegex = false;
    protected int $length;

    /**
     * NB: when passed a header name regex, returns true if at _least one_ header is long $length or more
     * @param string $headerName
     * @param int $length
     * @throws \Exception
     */
    public function __construct(string $headerName, int $length, bool $expandWildcardsInName = false)
    {
        $this->length = $length;
        $this->headerNameIsRegex = $expandWildcardsInName;

        if ($expandWildcardsInName) {
            $this->headerName = $this->regexpDelimiter . $this->wildcardStringToRegexp($headerName, true) . $this->regexpDelimiter . 'i';
        } else {
            $this->headerName = strtolower($headerName);
        }
    }

    public function matchesMessage(MessageInterface $message): bool
    {
        if ($this->headerNameIsRegex) {
            // Returns true when all headers
            foreach ($message->getHeaders() as $headerName => $headerValues) {
                if (preg_match($this->headerName, $headerName)) {
                    if (strlen(implode(', ', $headerValues)) >= $this->length) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            if (!$message->hasHeader($this->headerName)) {
                return false;
            }
            $headerValues = $message->getHeader($this->headerName);
            return strlen(implode(', ', $headerValues)) >= $this->length;
        }
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardStringToRegexp($value);
    }
}
