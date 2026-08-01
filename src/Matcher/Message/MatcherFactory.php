<?php

namespace YAWAF\Core\Matcher\Message;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Exception\ConfigurationError;
use YAWAF\Core\Matcher\MatcherInterface;
use YAWAF\Core\Matcher\OptionAwareMatcherFactory;
use YAWAF\Core\Matcher\Response\StatusCodeMatcher;

/**
 * Used to share code for setting up those matchers that ar identical between request and response
 */
abstract class MatcherFactory extends OptionAwareMatcherFactory
{
    use LoggerAwareTrait;

    protected array $supportedMatcherTypes = [
        'body',
        'content_type',
        'http_header_length',
//        'http_header_rfc_compliant',
        'http_header_value',
        'status_code',
        'wildcard_http_header_length',
//        'wildcard_http_header_rfc_compliant',
        'wildcard_http_header_value',
    ];

    public function __construct(LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
    }

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
            case 'http_header_value':
            case 'wildcard_http_header_value':
                // code temporarily left in: using an alternative json format for header matchers spec...
                //if (!is_array($values) || count($values) !== 1) {
                //    throw new ConfigurationError("Invalid response matching configuration: '$type' should be followed with an object with 1 element only");
                //}
                //$hv = reset($values);
                //$hn = array_key_first($values);
                //if (!is_string($hn) || !(is_string($hv) || is_array($hv))) {
                //    throw new ConfigurationError("Invalid response matching configuration: '$type' should be followed with an object with 1 element: a string name, and a string or string[] for values");
                //}
                $hn = $this->getMatcherOptionByPosition($type, 1);
                $hv = $values;
                if ($hn === '') {
                    throw new ConfigurationError("Invalid request matching configuration: '$type' should be followed by a '/' and a header name");
                }
                $this->validateHeaderName($hn);
                if (!(is_string($hv) || is_array($hv))) {
                    throw new ConfigurationError("Invalid request matching configuration: '$type' should be followed with an object with 1 element: a string name, and a string or string[] for values");
                }
                $opts = $this->parseMatcherBooleanOptions($type, ['case_insensitive' => false, 'no_wildcards' => true]);
                $matcher = new HeaderValueMatcher($hn, $hv, $opts['case_insensitive'], $opts['no_wildcards'], str_starts_with($matcherType, 'wildcard_'));
                break;
            case 'http_header_length':
            case 'wildcard_http_header_length':
                $hn = $this->getMatcherOptionByPosition($type, 1);
                $hv = $values;
                if ($hn === '') {
                    throw new ConfigurationError("Invalid request matching configuration: '$type' should be followed by a '/' and a header name");
                }
                $this->validateHeaderName($hn);
                //if (!(is_int($hv) || ctype_digit($hv))) {
                //    throw new ConfigurationError("Invalid request matching configuration: the value for '$type' should be an integer");
                //}
                $matcher = new HeaderLengthMatcher($hn, $hv, str_starts_with($matcherType, 'wildcard_'));
                break;
//            case 'http_header_rfc_compliant':
//            case 'wildcard_http_header_rfc_compliant':
//                $hn = $this->getMatcherOptionByPosition($type, 1);
///// @todo... what to use as value? should it be always true, or the string 'rfc', in case we want to support other validations, or ...?
//                $hv = $values;
//                if ($hn === '') {
//                    throw new ConfigurationError("Invalid request matching configuration: '$type' should be followed by a '/' and a header name");
//                }
//                $this->validateHeaderName($hn);
//                //if (!(is_int($hv) || ctype_digit($hv))) {
//                //    throw new ConfigurationError("Invalid request matching configuration: the value for '$type' should be an integer");
//                //}
//                $matcher = new HeaderRFCComplianceMatcher($hn, str_starts_with($matcherType, 'wildcard_'));
//                break;
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

    /**
     * @throws ConfigurationError
     */
    protected function validateHeaderName(string $hn): void
    {
        /// @todo improve validation - reject headers with any chars which are not valid in the rfc? Also, move to a shared function
        /// @todo improve validation - reject headers with any chars which are not valid in the rfc?
        if (trim($hn) !== $hn) {
            throw new ConfigurationError("Invalid request matching configuration: header name for '$type' should not contain whitespace");
        }
    }
}
