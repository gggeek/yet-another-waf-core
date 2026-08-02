<?php
declare(strict_types=1);

namespace YAWAF\Core\Http;

enum HeaderFormat: string
{
    case Cookie = 'cookie';
    case Date = 'date';
    case Generic = 'generic';
    case Integer = 'integer';
    case Json = 'json';
    case SFItem = 'Item';
    case SFList = 'List';
    case SFDictionary = 'Dictionary';
    case Token = 'token';
}
