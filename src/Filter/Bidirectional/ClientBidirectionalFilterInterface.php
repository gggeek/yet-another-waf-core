<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Bidirectional;

use YAWAF\Core\Filter\Request\ClientRequestFilterInterface;
use YAWAF\Core\Filter\Response\ResponseFilterInterface;

/**
 * A custom take on Psr\Http\Server\MiddlewareInterface.
 * In this case it is a RequestHandler or Middleware running a chain of Filters, instead of the Filters getting the handler injected.
 */
interface ClientBidirectionalFilterInterface extends ClientRequestFilterInterface, ResponseFilterInterface
{
}
