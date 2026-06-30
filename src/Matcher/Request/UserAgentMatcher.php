<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use YAWAF\Core\Matcher\Message\HeaderMatcher;

class UserAgentMatcher extends HeaderMatcher
{
    /**
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string|array $filter, bool $caseInsensitive = false, bool $expandWildcards = true)
    {
        parent::__construct('user-agent', $filter, $caseInsensitive, $expandWildcards);
    }
}
