<?php

/**
 * @author      Sebastian Kroczek <me@xbug.de>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

declare(strict_types=1);

namespace League\OAuth2\Server\Entities\Traits;

trait ClaimEntityTrait
{
    protected string $name;

    protected mixed $value;

    /**
     * Returns the name of the claim
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the claims value
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
