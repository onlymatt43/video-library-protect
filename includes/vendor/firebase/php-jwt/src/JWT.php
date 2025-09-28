<?php

namespace Firebase\JWT;

use DomainException;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * JSON Web Token implementation, based on this spec:
 * https://tools.ietf.org/html/rfc7519
 *
 * PHP version 5
 *
 * @category Authentication
 * @package  Authentication_JWT
 * @author   Neuman Vong <neuman@twilio.com>
 * @author   Anant Narayanan <anant@php.net>
 * @license  http://opensource.org/licenses/BSD-3-Clause 3-clause BSD
 * @link     https://github.com/firebase/php-jwt
 */
class JWT
{
    const ASN1_INTEGER = 0x02;
    const ASN1_SEQUENCE = 0x30;
    const ASN1_BIT_STRING = 0x03;

    /**
     * When checking nbf, iat or expiration times,
     * we want to provide some extra leeway time to
     * account for clock skew.
     */
    public static $leeway = 0;

    /**
     * Allow the current timestamp to be specified.
     * Useful for fixing a value within unit testing.
     */
    public static $timestamp = null;

    public static $supported_algs = array(
        'ES384' => array('openssl', 'SHA384'),
        'ES256' => array('openssl', 'SHA256'),
        'ES256K' => array('openssl', 'SHA256'),
        'HS256' => array('hash_hmac', 'SHA256'),
        'HS384' => array('hash_hmac', 'SHA384'),
        'HS512' => array('hash_hmac', 'SHA512'),
        'RS256' => array('openssl', 'SHA256'),
        'RS384' => array('openssl', 'SHA384'),
        'RS512' => array('openssl', 'SHA512'),
        'EdDSA' => array('sodium_crypto', 'EdDSA'),
    );

    /**
     * Decodes a JWT string into a PHP object.
     *
     * @param string                    $jwt            The JWT
     * @param Key|ArrayAccess|array     $keyOrKeyArray  The Key or associative array of key IDs (kid) to Key objects.
     *                                                  If the algorithm used is asymmetric, this is the public key
     *                                                  Each Key object contains an algorithm and matching key.
     *                                                  Supported algorithms are 'ES384','ES256', 'ES256K', 'HS256',
     *                                                  'HS384', 'HS512', 'RS256', 'RS384', and 'RS512'
     * @param array                     $allowed_algs   List of supported verification algorithms
     *                                                  Supported algorithms are 'ES384','ES256', 'ES256K', 'HS256',
     *                                                  'HS384', 'HS512', 'RS256', 'RS384', and 'RS512'
     *
     * @return stdClass The JWT's payload as a PHP object
     *
     * @throws InvalidArgumentException     Provided key/key-array is empty or malformed.
     * @throws DomainException              Provided JWT is malformed
     * @throws SignatureInvalidException    Provided JWT was signed with an invalid key
     * @throws BeforeValidException         Provided JWT is trying to be used before it's eligible as defined by 'nbf'
     * @throws BeforeValidException         Provided JWT is trying to be used before it was created as defined by 'iat'
     * @throws ExpiredException             Provided JWT has since expired, as defined by the 'exp' claim
     *
     * @uses jsonDecode
     * @uses urlsafeB64Decode
     */
    public static function decode($jwt, $keyOrKeyArray, array $allowed_algs = array())
    {
        $timestamp = \is_null(static::$timestamp) ? \time() : static::$timestamp;

        if (empty($keyOrKeyArray)) {
            throw new InvalidArgumentException('Key may not be empty');
        }
        if (!is_array($allowed_algs) || empty($allowed_algs)) {
            throw new InvalidArgumentException('Algorithm not allowed');
        }

        $tks = \explode('.', $jwt);
        if (\count($tks) != 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }
        list($headb64, $bodyb64, $cryptob64) = $tks;
        if (null === ($header = static::jsonDecode(static::urlsafeB64Decode($headb64)))) {
            throw new UnexpectedValueException('Invalid header encoding');
        }
        if (null === $payload = static::jsonDecode(static::urlsafeB64Decode($bodyb64))) {
            throw new UnexpectedValueException('Invalid claims encoding');
        }
        if (false === ($sig = static::urlsafeB64Decode($cryptob64))) {
            throw new UnexpectedValueException('Invalid signature encoding');
        }

        if (empty($header->alg)) {
            throw new UnexpectedValueException('Empty algorithm');
        }
        if (empty(static::$supported_algs[$header->alg])) {
            throw new UnexpectedValueException('Algorithm not supported');
        }
        if (!in_array($header->alg, $allowed_algs)) {
            throw new UnexpectedValueException('Algorithm not allowed');
        }

        // Check the signing key
        if ($keyOrKeyArray instanceof Key) {
            $key = $keyOrKeyArray;
        } elseif (\is_array($keyOrKeyArray) || $keyOrKeyArray instanceof \ArrayAccess) {
            if (isset($header->kid)) {
                if (!isset($keyOrKeyArray[$header->kid])) {
                    throw new UnexpectedValueException('"kid" invalid, unable to lookup correct key');
                }
                $key = $keyOrKeyArray[$header->kid];
            } else {
                throw new UnexpectedValueException('"kid" empty, unable to lookup correct key');
            }
        } else {
            throw new InvalidArgumentException(
                'Key must be a string, resource, OpenSSLAsymmetricKey, OpenSSLCertificate, ' .
                'Firebase\JWT\Key, or an array of Firebase\JWT\Key objects'
            );
        }

        if (!$key instanceof Key) {
            throw new InvalidArgumentException(
                'Key must be an instance of Firebase\JWT\Key'
            );
        }

        // Check if the algorithm matches
        if ($header->alg !== $key->getAlgorithm()) {
            throw new UnexpectedValueException('Provided key\'s algorithm does not match token\'s algorithm.');
        }

        // Verify the signature
        if (!static::verify("$headb64.$bodyb64", $sig, $key->getKeyMaterial(), $header->alg)) {
            throw new SignatureInvalidException('Signature verification failed');
        }

        // Check the nbf if it is defined. This is the time that the
        // token can actually be used. If it's not yet that time, abort.
        if (isset($payload->nbf) && $payload->nbf > ($timestamp + static::$leeway)) {
            throw new BeforeValidException(
                'Cannot handle token prior to ' . \date(\DateTime::ISO8601, $payload->nbf)
            );
        }

        // Check that this token has been created before 'now'. This prevents
        // using tokens that have been created for later use (and haven't
        // correctly used the nbf claim).
        if (isset($payload->iat) && $payload->iat > ($timestamp + static::$leeway)) {
            throw new BeforeValidException(
                'Cannot handle token prior to ' . \date(\DateTime::ISO8601, $payload->iat)
            );
        }

        // Check if this token has expired.
        if (isset($payload->exp) && ($timestamp - static::$leeway) >= $payload->exp) {
            throw new ExpiredException('Expired token');
        }

        return $payload;
    }

    /**
     * Converts and signs a PHP array into a JWT string.
     *
     * @param array                     $payload PHP array
     * @param string|resource|OpenSSLAsymmetricKey|OpenSSLCertificate|Key $key The secret key.
     * @param string                    $alg     Supported algorithms are 'ES384','ES256', 'ES256K', 'HS256',
     *                                           'HS384', 'HS512', 'RS256', 'RS384', and 'RS512'
     * @param string                    $keyId
     * @param array                     $head    An array with header elements to attach
     *
     * @return string A signed JWT
     *
     * @uses jsonEncode
     * @uses urlsafeB64Encode
     */
    public static function encode($payload, $key, $alg = 'HS256', $keyId = null, $head = null)
    {
        $header = array('typ' => 'JWT', 'alg' => $alg);
        if ($keyId !== null) {
            $header['kid'] = $keyId;
        }
        if (isset($head) && \is_array($head)) {
            $header = \array_merge($head, $header);
        }
        $segments = array();
        $segments[] = static::urlsafeB64Encode(static::jsonEncode($header));
        $segments[] = static::urlsafeB64Encode(static::jsonEncode($payload));
        $signing_input = \implode('.', $segments);

        $signature = static::sign($signing_input, $key, $alg);
        $segments[] = static::urlsafeB64Encode($signature);

        return \implode('.', $segments);
    }

    /**
     * Sign a string with a given key and algorithm.
     *
     * @param string                                         $msg       The message to sign
     * @param string|resource|OpenSSLAsymmetricKey|OpenSSLCertificate  $key       The secret key.
     * @param string                                         $alg       Supported algorithms are 'ES384','ES256', 'ES256K', 'HS256',
     *                                                                  'HS384', 'HS512', 'RS256', 'RS384', and 'RS512'
     *
     * @return string An encrypted message
     *
     * @throws DomainException Unsupported algorithm or bad key was specified
     */
    public static function sign($msg, $key, $alg = 'HS256')
    {
        if (empty(static::$supported_algs[$alg])) {
            throw new DomainException('Algorithm not supported');
        }
        list($function, $algorithm) = static::$supported_algs[$alg];
        switch ($function) {
            case 'hash_hmac':
                if (!is_string($key)) {
                    throw new InvalidArgumentException('key must be a string for HMAC algorithms');
                }
                return \hash_hmac($algorithm, $msg, $key, true);
            case 'openssl':
                $signature = '';
                $success = \openssl_sign($msg, $signature, $key, $algorithm);
                if (!$success) {
                    throw new DomainException("OpenSSL unable to sign data");
                }
                if ($alg === 'ES256' || $alg === 'ES256K') {
                    $signature = static::signatureToDER($signature);
                }
                if ($alg === 'ES384') {
                    $signature = static::signatureToDER($signature);
                }
                return $signature;
        }
    }

    /**
     * Verify a signature with the message, key and method. Not all methods
     * are symmetric, so we must have a separate verify and sign method.
     *
     * @param string                                       $msg         The original message (header and body)
     * @param string                                       $signature   The original signature
     * @param string|resource|OpenSSLAsymmetricKey|OpenSSLCertificate        $key         For HS*, a string key works. for RS*, must be an instance of OpenSSLAsymmetricKey
     * @param string                                       $alg         The algorithm
     *
     * @return bool
     *
     * @throws DomainException Invalid Algorithm, bad key, or OpenSSL failure
     */
    private static function verify($msg, $signature, $key, $alg)
    {
        if (empty(static::$supported_algs[$alg])) {
            throw new DomainException('Algorithm not supported');
        }

        list($function, $algorithm) = static::$supported_algs[$alg];
        switch ($function) {
            case 'openssl':
                $success = \openssl_verify($msg, $signature, $key, $algorithm);
                if ($success === 1) {
                    return true;
                } elseif ($success === 0) {
                    return false;
                }
                // returns 1 on success, 0 on failure, -1 on error.
                throw new DomainException(
                    'OpenSSL error: ' . \openssl_error_string()
                );
            case 'hash_hmac':
            default:
                if (!is_string($key)) {
                    throw new InvalidArgumentException('key must be a string for HMAC algorithms');
                }
                $hash = \hash_hmac($algorithm, $msg, $key, true);
                return static::constantTimeEquals($hash, $signature);
        }
    }

    /**
     * Decode a JSON string into a PHP object.
     *
     * @param string $input JSON string
     *
     * @return stdClass Object representation of JSON string
     *
     * @throws DomainException Provided string was invalid JSON
     */
    public static function jsonDecode($input)
    {
        $obj = \json_decode($input, false, 512, JSON_BIGINT_AS_STRING);

        if ($errno = \json_last_error()) {
            static::handleJsonError($errno);
        } elseif ($obj === null && $input !== 'null') {
            throw new DomainException('Null result with non-null input');
        }
        return $obj;
    }

    /**
     * Encode a PHP array into a JSON string.
     *
     * @param array $input A PHP array
     *
     * @return string JSON representation of the PHP array
     *
     * @throws DomainException Provided object could not be encoded to valid JSON
     */
    public static function jsonEncode($input)
    {
        if (\version_compare(PHP_VERSION, '5.4.0', '>=') && !(\defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)) {
            /** In PHP >=5.4.0, json_encode() options were introduced. */
            $json = \json_encode($input, \JSON_UNESCAPED_SLASHES);
        } else {
            /** PHP 5.3 only */
            $json = \json_encode($input);
        }
        if ($errno = \json_last_error()) {
            static::handleJsonError($errno);
        } elseif ($json === 'null' && $input !== null) {
            throw new DomainException('Null result with non-null input');
        }
        return $json;
    }

    /**
     * Decode a string with URL-safe Base64.
     *
     * @param string $input A Base64 encoded string
     *
     * @return string A decoded string
     *
     * @throws InvalidArgumentException invalid base64 characters
     */
    public static function urlsafeB64Decode($input)
    {
        $remainder = \strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= \str_repeat('=', $padlen);
        }
        return \base64_decode(\strtr($input, '-_', '+/'));
    }

    /**
     * Encode a string with URL-safe Base64.
     *
     * @param string $input The string you want encoded
     *
     * @return string The base64 encode of what you passed in
     */
    public static function urlsafeB64Encode($input)
    {
        return \str_replace('=', '', \strtr(\base64_encode($input), '+/', '-_'));
    }

    /**
     * Helper method to create a JSON error.
     *
     * @param int $errno An error number from json_last_error()
     *
     * @throws DomainException
     *
     * @return void
     */
    private static function handleJsonError($errno)
    {
        $messages = array(
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
        );
        throw new DomainException(
            isset($messages[$errno])
            ? $messages[$errno]
            : 'Unknown JSON error: ' . $errno
        );
    }

    /**
     * Get the number of bytes in cryptographic strings.
     *
     * @param string $str
     *
     * @return int
     */
    private static function safeStrlen($str)
    {
        if (\function_exists('mb_strlen')) {
            return \mb_strlen($str, '8bit');
        }
        return \strlen($str);
    }

    /**
     * Compare two strings using the same time whether they're equal or not.
     * This function should be used to mitigate timing attacks; for instance, when checking the result
     * of a cryptographic HMAC.
     *
     * @param string $safe The internal (safe) value to be checked
     * @param string $user The user submitted (unsafe) value
     *
     * @return bool True if the two strings are identical.
     */
    private static function constantTimeEquals($safe, $user)
    {
        if (\function_exists('hash_equals')) {
            return \hash_equals($safe, $user);
        }
        $safeLen = static::safeStrlen($safe);
        $userLen = static::safeStrlen($user);

        if ($userLen !== $safeLen) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < $userLen; ++$i) {
            $result |= (\ord($safe[$i]) ^ \ord($user[$i]));
        }

        // They are only identical strings if $result is exactly 0...
        return $result === 0;
    }

    /**
     * Convert an ECDSA signature to an ASN.1 DER sequence
     *
     * @param   string $sig The ECDSA signature to convert
     * @return  string The encoded DER object
     */
    private static function signatureToDER($sig)
    {
        // Separate the signature into r-value and s-value
        $length = max(1, (int) (\strlen($sig) / 2));
        list($r, $s) = \array_map('ltrim', \str_split($sig, $length), array("\x00", "\x00"));

        // Pad r-value and s-value
        $r = "\x00" . $r;
        $s = "\x00" . $s;

        return static::encodeDER(
            self::ASN1_SEQUENCE,
            static::encodeDER(self::ASN1_INTEGER, $r) .
            static::encodeDER(self::ASN1_INTEGER, $s)
        );
    }

    /**
     * Encodes a value into a DER object.
     *
     * @param   int     $type DER tag
     * @param   string  $value the value to encode
     * @return  string  the encoded object
     */
    private static function encodeDER($type, $value)
    {
        $tag_header = 0;
        if ($type === self::ASN1_SEQUENCE) {
            $tag_header |= 0x20;
        }

        // Type
        $der = \chr($tag_header | $type);

        // Length
        $der .= \chr(\strlen($value));

        return $der . $value;
    }
}