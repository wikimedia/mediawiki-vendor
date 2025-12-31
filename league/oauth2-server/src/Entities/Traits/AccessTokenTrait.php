<?php

/**
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

declare(strict_types=1);

namespace League\OAuth2\Server\Entities\Traits;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\CryptKeyInterface;
use League\OAuth2\Server\Entities\ClaimEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use RuntimeException;
use SensitiveParameter;

trait AccessTokenTrait
{
    private CryptKeyInterface $privateKey;

    private Configuration $jwtConfiguration;

    /**
     * Set the private key used to encrypt this access token.
     */
    public function setPrivateKey(
        #[SensitiveParameter]
        CryptKeyInterface $privateKey
    ): void {
        $this->privateKey = $privateKey;
    }

    /**
     * Initialise the JWT Configuration.
     */
    public function initJwtConfiguration(): void
    {
        $privateKeyContents = $this->privateKey->getKeyContents();

        if ($privateKeyContents === '') {
            throw new RuntimeException('Private key is empty');
        }

        $this->jwtConfiguration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($privateKeyContents, $this->privateKey->getPassPhrase() ?? ''),
            InMemory::plainText('empty', 'empty')
        );
    }

    /**
     * Generate a JWT from the access token
     */
    private function convertToJWT(): Token
    {
        $this->initJwtConfiguration();

        $builder = $this->jwtConfiguration->builder();
        $builder->permittedFor($this->getClient()->getIdentifier());
        $builder = $this->jwtConfiguration->builder()
            ->permittedFor($this->getClient()->getIdentifier())
            ->identifiedBy($this->getIdentifier())
            ->issuedAt(new DateTimeImmutable())
            ->canOnlyBeUsedAfter(new DateTimeImmutable())
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo($this->getSubjectIdentifier());

        foreach ($this->getClaims() as $claim) {
            /* @phpstan-ignore-next-line */
            $builder->withClaim($claim->getName(), $claim->getValue());
        }
        if (is_string($this->getIssuer())) {
            /* @phpstan-ignore-next-line */
            $builder->issuedBy($this->getIssuer());
        }

        return $builder
            // Set scope claim late to prevent it from being overridden.
            ->withClaim('scopes', $this->getScopes())
            ->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());
    }

    /**
     * Generate a string representation from the access token
     */
    public function toString(): string
    {
        return $this->convertToJWT()->toString();
    }

    abstract public function getClient(): ClientEntityInterface;

    abstract public function getExpiryDateTime(): DateTimeImmutable;

    /**
     * @return non-empty-string|null
     */
    abstract public function getUserIdentifier(): string|null;

    /**
     * @return ScopeEntityInterface[]
     */
    abstract public function getScopes(): array;

    /**
     * @return ClaimEntityInterface[]
     */
    abstract public function getClaims(): array;

    /**
     * @return non-empty-string
     */
    abstract public function getIdentifier(): string;

    /**
     * @return non-empty-string
     */
    private function getSubjectIdentifier(): string
    {
        return $this->getUserIdentifier() ?? $this->getClient()->getIdentifier();
    }

    abstract public function getIssuer(): ?string;
}
