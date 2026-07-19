<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Response;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Exception\ConfigurationError;
use YAWAF\Core\Matcher\MatcherFactoryInterface;
use YAWAF\Core\Matcher\MatcherInterface;
use YAWAF\Core\Matcher\Message\BodyMatcher;
use YAWAF\Core\Matcher\Message\ContentTypeMatcher;
use YAWAF\Core\Matcher\Message\HeaderMatcher;
use YAWAF\Core\Matcher\OptionAwareMatcherFactory;

class MatcherFactory extends OptionAwareMatcherFactory implements MatcherFactoryInterface
{
    use LoggerAwareTrait;

    protected array $supportedMatcherTypes = ['body', 'content_type', 'http_header', 'status_code', 'wildcard_http_header'];

    public function __construct(LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $type
     * @param mixed $values
     * @return \YAWAF\Core\Matcher\MatcherInterface
     * @throws \Exception
     */
    public function fromConfiguration(string $type, mixed $values): MatcherInterface
    {
        $matcherType = $this->getMatcherType($type);
        switch ($matcherType) {
            /// @todo accept 'response_body' as an alias?
            case 'body':
                $opts = $this->parseMatcherBooleanOptions($type, ['case_insensitive' => false, 'no_wildcards' => true]);
                $matcher = new BodyMatcher($values, $opts['case_insensitive'], $opts['no_wildcards']);
                break;
            /// @todo accept 'response_content_type' as an alias?
            case 'content_type':
                $opts = $this->parseMatcherBooleanOptions($type, ['no_wildcards' => true]);
                $matcher = new ContentTypeMatcher($values, $opts['no_wildcards']);
                break;
            /// @todo accept 'response_http_header' as an alias?
            case 'http_header':
            case 'wildcard_http_header':
                if (!is_array($values) || count($values) !== 1) {
                    throw new ConfigurationError("Invalid response matching configuration: '$type' should be followed with an object with 1 element only");
                }
                $hv = reset($values);
                $hn = array_key_first($values);
                if (!is_string($hn) || !(is_string($hv) || is_array($hv))) {
                    throw new ConfigurationError("Invalid response matching configuration: '$type' should be followed with an object with 1 element: a string name, and a string or string[] for values");
                }
                $opts = $this->parseMatcherBooleanOptions($type, ['case_insensitive' => false, 'no_wildcards' => true]);
                $matcher = new HeaderMatcher($hn, $hv, $opts['case_insensitive'], $opts['no_wildcards'], ($matcherType === 'wildcard_http_header'));
                break;
            case 'status_code':
                $opts = $this->parseMatcherBooleanOptions($type, ['no_wildcards' => true]);
                $matcher = new StatusCodeMatcher($values, $opts['no_wildcards']);
                break;
            default:
                throw new ConfigurationError("Invalid response matching configuration: '$type' => " . var_export($values, true));
        }
        if ($this->logger && $matcher instanceof LoggerAwareInterface) {
            $matcher->setLogger($this->logger);
        }
        return $matcher;
    }
}
