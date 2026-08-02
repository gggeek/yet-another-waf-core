<?php

namespace YAWAF\Core\Http;

enum HeaderFormat
{
    case Cookie;
    case Date;
    case Generic;
    case Integer;
    case Json;
    case SFItem;
    case SFList;
    case SFDictionary;
    case Token;
}
