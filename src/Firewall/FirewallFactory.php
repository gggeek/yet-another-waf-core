<?php

namespace YAWAF\Core\Firewall;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Logger\PrivateLoggerTrait;

class FirewallFactory
{
    use LoggerAwareTrait;
    use PrivateLoggerTrait;

    protected array|null $fallbackConfiguration = null;

    public function __construct(LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $configurationFile
     * @return Firewall
     * @throws \Exception
     * @todo allow parsing yaml files besides json ones
     */
    public function fromConfigFile(string $configurationFile): Firewall
    {
        $this->info("Loading firewall configuration from file '$configurationFile'");
        if (($configString = @file_get_contents($configurationFile)) === false) {
            throw new \Exception("Can not load configuration file '$configurationFile' " . error_get_last()['message']);
        }
        return $this->fromConfigString($configString);
    }

    /**
     * @param string $configuration
     * @return static
     * @throws \Exception
     */
    public function fromConfigString(string $configuration): Firewall
    {
        //$this->debug("Loading firewall configuration from string");
        if (trim($configuration) === '') {
            $config = [];
        } else {
            $config = @json_decode($configuration, true);
            if (!is_array($config)) {
                throw new \Exception("The configuration passed in is not a valid json array. Error: " . json_last_error_msg());
            }
        }
        return $this->fromConfiguration($config);
    }

    /**
     * @param array $config
     * @return Firewall
     * @throws \Exception
     */
    public function fromConfiguration(array $config): Firewall
    {
        if (!$config) {
            $this->warning("Empty configuration passed in. The firewall will only let trough 'ping' API calls");
        }

        foreach($config as $ruleName => $ruleConfig) {
            if (!is_array($ruleConfig)) {
                throw new \Exception("Bad configuration: the value for firewall rule '$ruleName' should be an array");
            }
        }

        if (array_key_exists('*', $config)) {
            // add the fallback rules
            $fallbackConfig = $config['*'] + $this->getFallbackConfiguration();
            // make sure that this is the last rule
            unset($config['*']);
            $config['*'] = $fallbackConfig;

        } else {
            $config['*'] = $this->getFallbackConfiguration();
        }

        $ruleFactory = new RuleFactory($this->logger);
        $rules = [];
        foreach($config as $ruleName => $ruleSpec) {
            try {
                $rule = $ruleFactory->fromConfiguration($ruleSpec);
                //if ($logger && $rule instanceof LoggerAwareInterface) {
                //    $rule->setLogger($logger);
                //}
                $rules[$ruleName] = $rule;
            } catch (\Exception $e) {
                throw new \Exception("Error parsing firewall rule '$ruleName': " . $e->getMessage());
            }
        }

        return new Firewall($rules, $this->logger);
    }

    /**
     * Returns the default filter applied to all clients - let ping and version requests through
     * @return array
     * @todo allow access to /events?
     */
    protected function getFallbackConfiguration(): array
    {
        return is_array($this->fallbackConfiguration) ? $this->fallbackConfiguration : Firewall::DefaultFallbackConfiguration;
    }

    public function setFallbackConfiguration(array $config): void
    {
        /// @todo validate the config now instead of relying on static::fromConfiguration
        $this->fallbackConfiguration = $config;
    }
}
