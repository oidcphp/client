<?php

namespace OpenIDConnect\Jwt;

use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Encryption\JWETokenSupport;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use OpenIDConnect\Metadata\ProviderMetadata;

class Factory
{
    use AlgorithmFactoryTrait;

    /**
     * @var ProviderMetadata
     */
    private $providerMetadata;

    public function __construct(ProviderMetadata $providerMetadata)
    {
        $this->providerMetadata = $providerMetadata;
    }

    /**
     * @return AlgorithmManager
     */
    public function createAlgorithmManager(): AlgorithmManager
    {
        return AlgorithmManager::create(
            $this->createSignatureAlgorithms($this->providerMetadata->idTokenAlgValuesSupported())
        );
    }

    /**
     * @return HeaderCheckerManager
     */
    public function createHeaderCheckerManager(): HeaderCheckerManager
    {
        $tokenTypesSupport = [new JWSTokenSupport()];

        if (null !== $this->providerMetadata->idTokenEncryptionAlgValuesSupported()) {
            $tokenTypesSupport[] = new JWETokenSupport();
        }

        return HeaderCheckerManager::create([
            new AlgorithmChecker($this->providerMetadata->idTokenAlgValuesSupported()),
        ], $tokenTypesSupport);
    }
}
