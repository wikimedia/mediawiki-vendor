<?php

/**
 * @author      Sebastian Kroczek <me@xbug.de>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

declare(strict_types=1);

namespace League\OAuth2\Server\Entities;

interface ClaimEntityInterface
{
    /**
     * Get the claim's name.
     */
    public function getName(): string;

    /**
     * Get the claim's value
     */
    public function getValue(): mixed;
}
