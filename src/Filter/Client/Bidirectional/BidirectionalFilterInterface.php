<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Client\Bidirectional;

use YAWAF\Core\Filter\Client\Request\RequestFilterInterface;
use YAWAF\Core\Filter\Client\Response\ResponseFilterInterface;

/**
 * A custom take on Psr\Http\Server\MiddlewareInterface.
 * In this case it is a RequestHandler running a chain of Filters, instead of the Filters getting the handler injected.
 */
interface BidirectionalFilterInterface extends RequestFilterInterface, ResponseFilterInterface
{
}
