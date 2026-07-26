<?php

namespace YAWAF\Core\Http;

/**
 * Actions taken by the HeaderParser when encountering an invalid value.
 * @todo... not all actions make sense for all types of errors or of parsing scenarios (validate vs. normalize). Can we
 *          figure out a better API than this?
 */
enum HeaderParserOnError
{
    case Throw;
    case ReturnNull;
    case ReplaceWithSpace;
    case Ignore;
}
