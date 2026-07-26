<?php

namespace YAWAF\Core\Http;

/**
 * Actions taken by the HeaderParser when encountering an invalid value
 */
enum HeaderParserOnError
{
    case Throw;
    case ReturnNull;
    /// @todo... allow replacing bad/unexpected chars with a specified one, eg. space or underscore
    //case ReplaceWithSpace;
}
