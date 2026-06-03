<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Message;

class ContentTypeMatcher extends HeaderMatcher
{
    /**
     * @param string|string[] $filter
     * @throws \Exception
     */
    public function __construct(string|array $filter)
    {
        parent::__construct('content-type', $filter);
    }
}
