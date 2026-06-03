<?php

namespace YAWAF\Core\Firewall;

enum RuleAction: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    /// @todo
    //case Rerun = 'rerun';
}
