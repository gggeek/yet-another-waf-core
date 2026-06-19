<?php
declare(strict_types=1);

namespace YAWAF\Core\Firewall;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Filter\Bidirectional\BidirectionalFilterInterface;
use YAWAF\Core\Logger\PrivateLoggerTrait;
use YAWAF\Core\Stdlib;

/**
 * The class doing the actual filtering of Requests and Responses
 */
class Firewall implements BidirectionalFilterInterface, LoggerAwareInterface
{
    public const DefaultFallbackConfiguration = [
        'req_match' => [
            'url' => '/_ping', // /version gets disabled out of the box - in case the version number might be useful to attackers...
            'http_method' => ['GET', 'HEAD'],
        ],
        'req_filters' => [],
        'req_action' => 'allow',
        'resp_match' => ['always' => true],
        'resp_action' => 'allow',
        'resp_filters' => [],
    ];

    use LoggerAwareTrait;
    use PrivateLoggerTrait;

    /** @var Rule[] */
    protected array $rules;
    protected null|Rule $currentRule = null;

    /**
     * @param Rule[] $rules
     * @throws \Exception
     */
    public function __construct(array $rules, LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
        if (!Stdlib::array_of($rules, Rule::class)) {
            throw new \Exception("Array passed to " . static::class . " constructor must contain only instances of " . Rule::class);
        }
        /// @todo remove this warning if implementing an `addRule` method
        if (!$rules) {
            $this->warning("Firewall was set up with no rules. This is most likely not what you wanted...");
        }
        $this->rules = $rules;
    }

    public function filterRequest(ServerRequestInterface $request): ServerRequestInterface|false
    {
        $this->currentRule = null;
        foreach ($this->rules as $ruleName => $rule) {
            if ($rule->matchesRequest($request)) {
                $this->debug("Firewall rule '$ruleName' matched request: " . $this->request2Log($request));
                $this->currentRule = $rule;
                return $rule->filterRequest($request);
            }
        }
        $this->debug("No firewall rule matched request: " . $this->request2Log($request));
        return false;
    }

    public function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface|false
    {
        $response = $this->currentRule->filterResponse($response, $request);
        $this->currentRule = null;
        return $response;
    }

    // *** Logging ***

    protected function request2Log(ServerRequestInterface $request): string
    {
        return $request->getMethod() . ' ' . $request->getUri();
    }
}
