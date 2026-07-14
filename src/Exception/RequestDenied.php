<?php
declare(strict_types=1);

namespace YAWAF\Core\Exception;

/**
 * The exception thrown when no firewall rule matches, or when a "deny" rule does
 */
class RequestDenied extends \Exception
{
}
