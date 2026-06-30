<?php

namespace YAWAF\Core\Matcher\Message;

use Psr\Http\Message\MessageInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class BodyMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;

    /**
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string|array $filter, bool $caseInsensitive = false, bool $expandWildcards = true)
    {
        $this->caseInsensitive = $caseInsensitive;
        $this->expandWildcards = $expandWildcards;
        $this->setMatchingValues($filter);
    }

    public function matchesMessage(MessageInterface $message): bool
    {
        return $this->matchesRegexp($message->getBody());
    }

}
