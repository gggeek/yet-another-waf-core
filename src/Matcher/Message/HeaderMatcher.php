<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Message;


use Psr\Http\Message\MessageInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class HeaderMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    protected string $headerName;

    /**
     * @param string $headerName
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string $headerName, string|array $filter)
    {
        $this->headerName = $headerName;
        $this->setMatchingValues($filter);
    }

    public function matchesMessage(MessageInterface $message): bool
    {
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

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardToRegexp($value);
    }
}
