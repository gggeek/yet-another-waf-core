<?php

namespace YAWAF\Core\Filter\Bidirectional;

class RemoveAcceptEncoding extends RequestHeaderRemover
{
    public function __construct()
    {
        $this->overrideHeaders = ['Accept-Encoding'];
    }
}
