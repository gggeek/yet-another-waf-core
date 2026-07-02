<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Server\Bidirectional;

use YAWAF\Core\Filter\Server\Request\RequestFilterInterface;
use YAWAF\Core\Filter\Server\Response\ResponseFilterInterface;

/**
 * A custom take on Psr\Http\Server\MiddlewareInterface.
 * In this case it is a RequestHandler or Middleware running a chain of Filters, instead of the Filters getting the handler injected.
 */
interface BidirectionalFilterInterface extends RequestFilterInterface, ResponseFilterInterface
{
}
