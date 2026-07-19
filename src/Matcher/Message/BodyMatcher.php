<?php

namespace YAWAF\Core\Matcher\Message;

use Psr\Http\Message\MessageInterface;
use YAWAF\Core\Http\BodyCompressorTrait;
use YAWAF\Core\Matcher\RegExpListMatcherTrait;

class BodyMatcher extends BaseMatcher
{
    use RegExpListMatcherTrait;
    use BodyCompressorTrait;

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

    /**
     * @throws \YAWAF\Core\Exception\RequestBodyCantBeDecompressed
     * @throws \YAWAF\Core\Exception\ResponseBodyCantBeDecompressed
     */
    public function matchesMessage(MessageInterface $message): bool
    {
/// @todo... inject/save the inflated message body for further reuse: when $message is a ServerRequestInterface using an attribute,
///          when it's a RequestInterface wrap it in a custom descendant class which adds set/getAttributes
        if ($this->messageBodyIsCompressed($message) /*|| $this->messageBodyIsChunked($message)*/) {
            $body = $this->decompressMessageBody($message);
        } else {
            $stream = $message->getBody();
            $stream->rewind();
            $body = $stream->getContents();
        }

        return $this->matchesRegexp($body);
    }

    protected function normalizeMatchingRegexp(string $value): string
    {
        return $this->wildcardStringToRegexp($value);
    }
}
