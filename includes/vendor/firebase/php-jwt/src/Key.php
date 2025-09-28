<?php

namespace Firebase\JWT;

use InvalidArgumentException;

/**
 * A simple object to represent a key used by JWT
 */
class Key
{
    /** @var string|resource|\OpenSSLAsymmetricKey|\OpenSSLCertificate */
    private $keyMaterial;

    /** @var string */
    private $algorithm;

    /**
     * @param string|resource|\OpenSSLAsymmetricKey|\OpenSSLCertificate $keyMaterial
     * @param string $algorithm
     */
    public function __construct($keyMaterial, string $algorithm)
    {
        if (
            !\is_string($keyMaterial)
            && !\is_resource($keyMaterial)
            && !($keyMaterial instanceof \OpenSSLAsymmetricKey)
            && !($keyMaterial instanceof \OpenSSLCertificate)
        ) {
            throw new InvalidArgumentException(
                'Key material must be a string, resource, or OpenSSLAsymmetricKey'
            );
        }

        if (empty($algorithm)) {
            throw new InvalidArgumentException('Algorithm cannot be empty');
        }

        $this->keyMaterial = $keyMaterial;
        $this->algorithm = $algorithm;
    }

    /**
     * Return the algorithm used for this key
     *
     * @return string
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * Return the key material
     *
     * @return string|resource|\OpenSSLAsymmetricKey|\OpenSSLCertificate
     */
    public function getKeyMaterial()
    {
        return $this->keyMaterial;
    }
}