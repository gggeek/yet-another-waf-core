<?php

namespace YAWAF\Core\Http;

trait HeaderParserAwareTrait
{
    protected HeaderParser $headerParser;

    public function setHeaderParser(HeaderParser $headerParser)
    {
        $this->headerParser = $headerParser;
    }
}
