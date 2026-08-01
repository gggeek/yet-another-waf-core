<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Exception\ConfigurationError;
use YAWAF\Core\Matcher\MatcherFactoryInterface;
use YAWAF\Core\Matcher\MatcherInterface;
//use YAWAF\Core\Matcher\Message\BodyMatcher;
//use YAWAF\Core\Matcher\Message\ContentTypeMatcher;
//use YAWAF\Core\Matcher\Message\HeaderValueMatcher;
use YAWAF\Core\Matcher\Message\MatcherFactory as BaseMatcherFactory;

class MatcherFactory extends BaseMatcherFactory implements MatcherFactoryInterface
{
    //use LoggerAwareTrait;

    public function __construct(LoggerInterface|null $logger = null)
    {
        parent::__construct($logger);

        $this->supportedMatcherTypes = array_merge($this->supportedMatcherTypes, [
            'client_address',
            'client_port',
            'content_type',
            'host',
            'http_method',
            'port',
            'protocol_version',
            'query_string',
            'scheme',
            'url_path',
            'user_agent',
            'wildcard_query_string'
        ]);
    }

    /**
     * @throws \Exception
     */
    public function fromConfiguration(string $type, mixed $values): MatcherInterface
    {
        $matcherType = $this->getMatcherType($type);
        switch ($matcherType) {
            /// @todo accept 'request_body' as an alias?
//            case 'body':
//                $opts = $this->parseMatcherBooleanOptions($type, ['case_insensitive' => false, 'no_wildcards' => true]);
//                $matcher = new BodyMatcher($values, $opts['case_insensitive'], $opts['no_wildcards']);
//                break;
            case 'client_address':
                $opts = $this->parseMatcherBooleanOptions($type, ['no_wildcards' => true]);
                $matcher = new ClientAddressMatcher($values, $opts['no_wildcards']);
                break;
            case 'client_port':
                $opts = $this->parseMatcherBooleanOptions($type, ['no_wildcards' => true]);
                $matcher = new ClientPortMatcher($values, $opts['no_wildcards']);
                break;
            /// @todo accept 'request_content_type' as an alias?
//            case 'content_type':
//                $opts = $this->parseMatcherBooleanOptions($type, ['no_wildcards' => true]);
//                $matcher = new ContentTypeMatcher($values, $opts['no_wildcards']);
//                break;
            case 'host':
                $opts = $this->parseMatcherBooleanOptions($type, ['no_wildcards' => true]);
                $matcher = new HostMatcher($values, $opts['no_wildcards']);
                break;
            /// @todo accept 'request_http_header' as an alias?
//            case 'http_header':
//            case 'wildcard_http_header':
//            // code temporarily left in: using an alternative json format for header matchers spec...
//                //if (!is_array($values) || count($values) !== 1) {
//                //    throw new ConfigurationError("Invalid request matching configuration: '$type' should be followed with an object with 1 element only");
//                //}
//                //$hv = reset($values);
//                //$hn = array_key_first($values);
//                //if (!is_string($hn) || !(is_string($hv) || is_array($hv))) {
//                //    throw new ConfigurationError("Invalid request matching configuration: '$type' should be followed with an object with 1 element: a string name, and a string or string[] for values");
//                //}
//                $hn = $this->getMatcherOptionByPosition($type, 1);
//                $hv = $values;
//                if ($hn === '') {
//                    throw new ConfigurationError("Invalid request matching configuration: '$type' should be followed by a '/' and a header name");
//                }
//                /// @todo improve validation - reject headers with any chars which are not valid in the rfc?
//                if (trim($hn) !== $hn) {
//                    throw new ConfigurationError("Invalid request matching configuration: header name for '$type' should not contain whitespace");
//                }
//                if (!(is_string($hv) || is_array($hv))) {
//                    throw new ConfigurationError("Invalid request matching configuration: '$type' should be followed with an object with 1 element: a string name, and a string or string[] for values");
//                }
//                $opts = $this->parseMatcherBooleanOptions($type, ['case_insensitive' => false, 'no_wildcards' => true]);
//                $matcher = new HeaderValueMatcher($hn, $values, $opts['case_insensitive'], $opts['no_wildcards'], ($matcherType === 'wildcard_http_header'));
//                break;
            /// @todo accept 'method' as an alias?
            case 'http_method':
                $matcher = new MethodMatcher($values);
                break;
            case 'port':
                $opts = $this->parseMatcherBooleanOptions($type, ['no_wildcards' => true]);
                $matcher = new PortMatcher($values, $opts['no_wildcards']);
                break;
            case 'protocol_version':
                $matcher = new ProtocolVersionMatcher($values);
                break;
/// @todo rename?
            case 'query_string':
            case 'wildcard_query_string':
                if (!is_array($values) || count($values) !== 1) {
                    throw new ConfigurationError("Invalid request matching configuration: '$type' should be followed with an object with 1 element only");
                }
                $qsv = reset($values);
                $qsn = array_key_first($values);
                if (!is_string($qsn) || !(is_string($qsv) || is_array($qsv))) {
                    throw new ConfigurationError("Invalid request matching configuration: '$type' should be followed with an object with 1 element: a string name, and a string or string[] for values");
                }
                $opts = $this->parseMatcherBooleanOptions($type, ['case_insensitive' => false, 'no_wildcards' => true]);
                $matcher = new QueryStringMatcher($qsn, $qsv, $opts['case_insensitive'], $opts['no_wildcards'], str_starts_with($matcherType, 'wildcard_'));
                break;
            case 'scheme':
                $opts = $this->parseMatcherBooleanOptions($type, ['no_wildcards' => true]);
                $matcher = new SchemeMatcher($values, $opts['no_wildcards']);
                break;
            /// @todo accept 'path' as an alias?
            case 'url_path':
                $opts = $this->parseMatcherBooleanOptions($type, ['case_insensitive' => false, 'no_wildcards' => true]);
                $matcher = new PathMatcher($values, '', $opts['case_insensitive'], $opts['no_wildcards']);
                break;
            case 'user_agent':
                $opts = $this->parseMatcherBooleanOptions($type, ['case_insensitive' => false, 'no_wildcards' => true]);
                $matcher = new UserAgentMatcher($values, $opts['case_insensitive'], $opts['no_wildcards']);
                break;
            default:
                //throw new ConfigurationError("Invalid request matching configuration: '$type' => " . var_export($values, true));
                return parent::fromConfiguration($type, $values);
        }
        if ($this->logger && $matcher instanceof LoggerAwareInterface) {
            $matcher->setLogger($this->logger);
        }
        return $matcher;
    }
}
