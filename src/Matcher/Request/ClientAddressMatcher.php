<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;
use YAWAF\Core\ServerRequest\Psr7\Attributes;

class ClientAddressMatcher extends BaseMatcher
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
        /// @todo... log a warning if we are not passed the attributes bag or this specific attribute
        $clientAddress = $request->getAttribute(Attributes::class)?->get(Attributes::REMOTE_ADDR) ?? '';
        return $this->matchesRegexp($clientAddress);
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardToRegexp($value);
    }
}
