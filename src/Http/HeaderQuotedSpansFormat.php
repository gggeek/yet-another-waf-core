<?php

namespace YAWAF\Core\Http;

enum HeaderQuotedSpansFormat
{
    case None; // no special handling for spans delimited by double quotes
    case QuotedString; // any char can be escaped with a backslash
    case StructuredField; // only DQ and backslash can be escaped with a backslash
}
