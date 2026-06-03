<?php

namespace YAWAF\Core\Firewall;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Logger\PrivateLoggerTrait;
use YAWAF\Core\Matcher\ChainFactory;
use YAWAF\Core\Matcher\Logic\AndMatcher;
use YAWAF\Core\Matcher\Logic\MatcherFactory as LogicMatcherFactory;
use YAWAF\Core\Matcher\MatcherFactoryInterface;
use YAWAF\Core\Matcher\Request\MatcherFactory as RequestMatcherFactory;
use YAWAF\Core\Matcher\Request\RequestMatcherInterface;
use YAWAF\Core\Matcher\Response\MatcherFactory as ResponseMatcherFactory;
use YAWAF\Core\Matcher\Response\ResponseMatcherInterface;

class RuleFactory
{
    use LoggerAwareTrait;
    use PrivateLoggerTrait;

    protected MatcherFactoryInterface|null $requestMatcherFactory = null;
    protected MatcherFactoryInterface|null $responseMatcherFactory = null;

    public function __construct(LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @param array $config
     * @return Rule
     * @throws \Exception
     */
    public function fromConfiguration(array $config): Rule
    {
        if (!$config) {
            throw new \Exception("Bad configuration: the value for firewall rule should not be an empty array");
        }

        // Allow 'simplified' configuration
        if (!array_key_exists('req_match', $config) && !array_key_exists('req_action', $config) && !array_key_exists('req_filters', $config)
            && !array_key_exists('resp_match', $config) && !array_key_exists('resp_action', $config) && !array_key_exists('resp_filters', $config)) {
            $config = ['req_match' => $config];
        }

        if ($badKeys = array_diff(array_keys($config), ['req_match', 'req_action', 'req_filters', 'resp_match', 'resp_action', 'resp_filters'])) {
            throw new \Exception("Bad configuration: the value for firewall rule should not have keys: " . implode(',', $badKeys));
        }

        $config = $config + [
            'req_match' => ['always' => true],
            'req_filters' => [],
            'resp_match' => ['always' => true],
            'resp_filters' => []
        ];

        if (array_key_exists('req_action', $config)) {
            $requestAction = RuleAction::tryFrom($config['req_action']);
            if ($requestAction === null) {
                throw new \Exception("Bad configuration: unsupported value for req_action '{$config['req_action']}'");
            }
        } else {
            $requestAction = RuleAction::Allow;
        }

        if (array_key_exists('resp_action', $config)) {
            $responseAction = RuleAction::tryFrom($config['resp_action']);
            if ($requestAction === null) {
                throw new \Exception("Bad configuration: unsupported value for resp_action '{$config['resp_action']}'");
            }
        } else {
            $responseAction = RuleAction::Allow;
        }

        $requestMatcherFactory = $this->getRequestMatcherFactory([]);
        $responseMatcherFactory = $this->getResponseMatcherFactory([]);

        $rule = new Rule(
            $this->parseMatcherConfiguration($config['req_match'], $requestMatcherFactory),
            $this->parseRequestFiltersConfiguration($config['req_filters']),
            $requestAction,
            $this->parseMatcherConfiguration($config['resp_match'], $responseMatcherFactory),
            $this->parseResponseFiltersConfiguration($config['resp_filters']),
            $responseAction
        );
        if ($this->logger && $rule instanceof LoggerAwareInterface) {
            $rule->setLogger($this->logger);
        }
        return $rule;
    }

    /**
     * @throws \Exception
     */
    protected function parseMatcherConfiguration(array $matcherSpec, MatcherFactoryInterface $matcherFactory): RequestMatcherInterface|ResponseMatcherInterface
    {
        if (!$matcherSpec) {
            throw new \Exception("The value for each rule 'match' section must be a non-empty array of conditions");
        }

        if (count($matcherSpec) === 1) {
            $matcher = $matcherFactory->fromConfiguration(array_key_first($matcherSpec), reset($matcherSpec));
        } else {
            $matcher = new AndMatcher([]);
            foreach ($matcherSpec as $type => $values) {
                $matcher->addMatcher($matcherFactory->fromConfiguration((string)$type, $values));
            }
        }
        return $matcher;
    }

    protected function parseRequestFiltersConfiguration(array $filtersSpec): array
    {
/// @todo...
        return [];
    }

    protected function parseResponseFiltersConfiguration(array $filtersSpec): array
    {
/// @todo...
        return [];
    }


    /**
     * @param array $config
     * @return \YAWAF\Core\Matcher\MatcherFactoryInterface
     * @throws \Exception
     */
    protected function getRequestMatcherFactory(array $config): MatcherFactoryInterface
    {
        if ($this->requestMatcherFactory === null) {
            $logicMatcherFactory = new LogicMatcherFactory($this->logger);
            $this->requestMatcherFactory = new ChainFactory([new RequestMatcherFactory($this->logger), $logicMatcherFactory]);
            // inception! ;-)
            $logicMatcherFactory->setMatcherFactory($this->requestMatcherFactory);
        }
        return $this->requestMatcherFactory;
    }

    /**
     * @param array $config
     * @return \YAWAF\Core\Matcher\MatcherFactoryInterface
     * @throws \Exception
     */
    protected function getResponseMatcherFactory(array $config): MatcherFactoryInterface
    {
        if ($this->responseMatcherFactory === null) {
            $logicMatcherFactory = new LogicMatcherFactory($this->logger);
            $this->responseMatcherFactory = new ChainFactory([new ResponseMatcherFactory($this->logger), $logicMatcherFactory]);
            // inception! ;-)
            $logicMatcherFactory->setMatcherFactory($this->responseMatcherFactory);
        }
        return $this->responseMatcherFactory;
    }
}
