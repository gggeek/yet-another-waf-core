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
     * @todo allow wildcards $parameterName, while allowing disabling separately wildcards for name and for value
     */
    public function __construct(string $headerName, string|array $filter, bool $caseInsensitive = false, bool $expandWildcards = true)
    {
        $this->caseInsensitive = $caseInsensitive;
        $this->expandWildcards = $expandWildcards;

/// @todo... throw if there are uppercase chars in the header name, as we always match against lower cased names

        if ($this->headerNameIsRegex) {
            $this->headerName = $this->regexpDelimiter . $this->wildcardStringToRegexp($headerName) . $this->regexpDelimiter;
        } else {
            $this->headerName = $headerName;
        }
        $this->setMatchingValues($filter);
    }

    public function matchesMessage(MessageInterface $message): bool
    {
        if ($this->headerNameIsRegex) {
            foreach ($message->getHeaders() as $headerName => $headerValues) {
                if (preg_match($this->headerName, $headerName)) {
                    return $this->matchesRegexp(implode(', ', $headerValues));
                }
            }
            return false;
        } else {
            if (!$message->hasHeader($this->headerName)) {
                return false;
            }
            return $this->matchesRegexp($message->getHeaderLine($this->headerName));
        }
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardStringToRegexp($value);
    }
}
