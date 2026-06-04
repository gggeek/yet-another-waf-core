<?php
declare(strict_types=1);

// An http server to be used as upstream backend by unit tests. _Do not use for anything else!_

/// @todo... allow usage of query string params or custom headers to force returning 40x, 50x responses, enabling/disabling http
///          features (compressed response bodies, keepalives, etc...)

header('Content-type: application/json');
echo json_encode(['result' => 'OK']);
