<?php

namespace YAWAF\Core\Http;

/**
 * @deprecated to be dropped
 */
enum HeaderParserOnError
{
    case Throw;
    case ReturnNull;
    case ReplaceWithSpace;
    case Ignore;
}
