# Gr4vy SDK for PHP

Gr4vy provides any of your payment integrations through one unified API. For
more details, visit [gr4vy.com](https://gr4vy.com).

## Installation

The Gr4vy PHP SDK can be installed via [Composer](https://getcomposer.org/).

```sh
composer require gr4vy/gr4vy-php
```

## Getting Started

To make your first API call, you will need to [request](https://gr4vy.com) a
Gr4vy instance to be set up. Please contact our sales team for a demo.

Once you have been set up with a Gr4vy account you will need to head over to the
**Integrations** panel and generate a private key. We recommend storing this key
in a secure location but in this code sample we simply read the file from disk.

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

$privateKeyLocation = __DIR__ . "/private_key.pem";

$config = new Gr4vy\Gr4vyConfig("[YOUR_GR4VY_ID]", $privateKeyLocation);

try {
    $result = $config->listBuyers();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling listBuyers: ', $e->getMessage(), PHP_EOL;
}
```

## Environment

The SDK defaults the environment to "sandbox", to send transactions to production, set the environment in `Gr4vyConfig`:

```php

$config = new Gr4vy\Gr4vyConfig("[YOUR_GR4VY_ID]", $privateKeyLocation, false, "sandbox");

$config = new Gr4vy\Gr4vyConfig("[YOUR_GR4VY_ID]", $privateKeyLocation, false, "production");

```

## Gr4vy Embed

To create a token for Gr4vy Embed, call the `config->getEmbedToken()` function
with the amount, currency, optional buyer information and optional checkout session for Gr4vy Embed.

```php
//A checkout session allows multiple transaction attempts to be tied together
$checkoutSession = $config->newCheckoutSession();

echo $config->getEmbedToken(
  array(
    "amount"=> 200,
    "currency" => "USD",
    "buyer_id"=> "d757c76a-cbd7-4b56-95a3-40125b51b29c"
  ), 
  $checkoutSession["id"]
);
```
Or, generate a checkout session and Embed Token with a single call:
```php
echo $config->getEmbedTokenWithCheckoutSession(
  array(
    "amount"=> 200,
    "currency" => "USD",
    "buyer_id"=> "d757c76a-cbd7-4b56-95a3-40125b51b29c"
  )
);
```

You can now pass this token to your front end where it can be used to
authenticate Gr4vy Embed.

The `buyerId` and `buyerExternalIdentifier` fields can be used to allow the
token to pull in previously stored payment methods for a user. A buyer needs to
be created before it can be used in this way.

```php
$config = new Gr4vy\Gr4vyConfig("[YOUR_GR4VY_ID]", $privateKeyLocation);

$buyer_request = array("external_identifier"=>"412231123","display_name"=>"Tester T.");
$buyer = $config->addBuyer($buyer_request);

$embed = array("amount"=> 200, "currency" => "USD", "buyer_id"=> $buyer["id"]);
$embedToken = $config->getEmbedToken($embed);
```

## Checkout Sessions

A checkout session can be used across Embed sessions to track retries or shopping cart updates.  To achieve this the same `checkoutSessionId` can be used in multiple `getEmbedToken` calls.

NOTE: a checkout session is valid for 1h from creation.

```php
$config->getEmbedToken(
  array(
    "amount"=> 200,
    "currency" => "USD",
    "buyer_id"=> "d757c76a-cbd7-4b56-95a3-40125b51b29c"
  ), 
  $storedCheckoutSessionId
);
```

## Initialization

The client can be initialized with the Gr4vy ID (`gr4vyId`) and the private key.

```php
$config = new Gr4vy\Gr4vyConfig("acme", $privateKeyLocation);
```

Alternatively, you can set the `host` of the server to use directly.

```php
$config = new Gr4vy\Gr4vyConfig("acme", $privateKeyLocation);
$config->setHost("https://api.acme.gr4vy.app");
```

Your API key can be created in your admin panel on the **Integrations** tab.

## Multi merchant

In a multi-merchant environment, the merchant account ID can be set by passing `merchantAccountId` to the Config:

```php
$config = new Gr4vy\Gr4vyConfig("[YOUR_GR4VY_ID]", $privateKeyLocation, false, "sandbox", "default");

$config = new Gr4vy\Gr4vyConfig("[YOUR_GR4VY_ID]", $privateKeyLocation, false, "sandbox", "my_merchant_account_id");
```

## Making API calls

This library conveniently maps every API path to a seperate function. For example, `GET /buyers?limit=100` would be:

```php
$result = $config->listBuyers(100);
```

To create or update a resource an `array` should be sent with the request data.

```php
$buyer_request = array("external_identifier"=>"412231123","display_name"=>"Tester T.");
$buyer = $config->addBuyer($buyer_request);
```

Similarly, to update a buyer you will need to pass in the `BuyerUpdateRequest`.

```php
$buyer_update = array("external_identifier"=>"testUpdateBuyer");
$result = $config->updateBuyer($result["id"], $buyer_update);
```

## Generate API bearer token

The SDK can be used to create API access tokens for use with other request
libraries.

```php
$bearerToken = Gr4vyConfig::getToken($privateKeyLocation, array("*.read", "*.write"))->toString();
```

The first parameter is the location of your private key. The second
parameter is an array of scopes for the token.

The resulting token can be used as a bearer token in the header of the HTTP
request made to the API.

```ini
Authorization: Bearer <bearerToken>
```

## Verify Webhook Signature

The SDK provides a method to verify the signature of incoming webhooks to ensure they are sent by Gr4vy.

```php
try {
    Gr4vyConfig::verifyWebhook(
        $secret,            // The webhook secret key
        $payload,           // The raw payload of the webhook
        $signatureHeader,   // The `X-Gr4vy-Webhook-Signatures` header from the webhook
        $timestampHeader,   // The `X-Gr4vy-Webhook-Timestamp` header from the webhook
        $timestampTolerance // Optional: Tolerance in seconds for timestamp validation
    );
    echo "Webhook verified successfully.";
} catch (Exception $e) {
    echo "Webhook verification failed: " . $e->getMessage();
}
```

### Exceptions

The `verifyWebhook` function will throw an exception in the following cases:

- Missing or empty `signatureHeader` or `timestampHeader`.
- Invalid `timestampHeader` (not numeric).
- Signature mismatch (no matching signature found).
- Timestamp too old (if `timestampTolerance` is set to non-0 value).

### Notes

- Ensure you store your webhook secret securely and do not expose it publicly.
- Use the `timestampTolerance` parameter to account for potential clock drift between servers.

## Logging & Debugging

The SDK makes it easy possible to the requests and responses to the console.

```js
$debugging = true;
$config = new Gr4vyConfig(self::$gr4vyId, self::$privateKeyLocation, $debugging);
```

This will print debug output for the request to the console.

## Development

### Tests

To run the tests, store a private key for the spider environment and then run
the following commands.

```bash
composer install
./vendor/bin/phpunit test/
```

### Publishing

Publishing of this project is done through [Packagist][packagist]. New versions
are released by creating a new Git tag.

## License

This library is released under the [MIT License](LICENSE).

[packagist]: https://packagist.org/packages/gr4vy/gr4vy-php
