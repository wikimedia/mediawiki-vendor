<?php

namespace Gr4vy\Test\Api;

use \Gr4vy\Gr4vyConfig;
use PHPUnit\Framework\TestCase;

class TokenApiTest extends TestCase
{
    private static $privateKeyLocation = __DIR__ . "/../../private_key.pem";
    private static $gr4vyId = "spider";

    /**
     * Setup before running any test cases
     */
    public static function setUpBeforeClass(): void
    {
    }

    /**
     * Setup before running each test case
     */
    public function setUp(): void
    {
    }

    /**
     * Clean up after running each test case
     */
    public function tearDown(): void
    {
    }

    /**
     * Clean up after running all test cases
     */
    public static function tearDownAfterClass(): void
    {
    }

    public function testGetToken()
    {
        try {
            $token = Gr4vyConfig::getToken(self::$privateKeyLocation, array("*.read"));
            $this->assertGreaterThan(0, strlen($token), "Expected length to be greater than 0.");
        } catch (Exception $e) {
            $this->fail("Exception thrown: " . $e->getMessage());
        }
    }

    public function testGetEmbedToken()
    {
        try {
            $config = new Gr4vyConfig(self::$gr4vyId, self::$privateKeyLocation);
            $embed = array("amount"=> 200, "currency" => "USD", "buyer_id"=> "d757c76a-cbd7-4b56-95a3-40125b51b29c");
            $embedToken = $config->getEmbedToken($embed);

            $this->assertGreaterThan(0, strlen($embedToken), "Expected length to be greater than 0.");
        } catch (Exception $e) {
            $this->fail("Exception thrown: " . $e->getMessage());
        }
    }

    public function testGetEmbedTokenWithCheckoutSessionPassedIn()
    {
        try {
            $config = new Gr4vyConfig(self::$gr4vyId, self::$privateKeyLocation);
            $checkoutSession = $config->newCheckoutSession();

            $embed = array("amount"=> 200, "currency" => "USD", "buyer_id"=> "d757c76a-cbd7-4b56-95a3-40125b51b29c");
            $embedToken = $config->getEmbedToken($embed, $checkoutSession["id"]);

            $this->assertGreaterThan(0, strlen($embedToken), "Expected length to be greater than 0.");
        } catch (Exception $e) {
            $this->fail("Exception thrown: " . $e->getMessage());
        }
    }

    public function testGetEmbedTokenWithCheckoutSession()
    {
        try {
            $config = new Gr4vyConfig(self::$gr4vyId, self::$privateKeyLocation);

            $embed = array("amount"=> 200, "currency" => "USD", "buyer_id"=> "d757c76a-cbd7-4b56-95a3-40125b51b29c");
            $embedToken = $config->getEmbedTokenWithCheckoutSession($embed);

            $this->assertGreaterThan(0, strlen($embedToken), "Expected length to be greater than 0.");
        } catch (Exception $e) {
            $this->fail("Exception thrown: " . $e->getMessage());
        }
    }


    public function testAddBuyerAndEmbed()
    {
        try {
            $config = new Gr4vyConfig(self::$gr4vyId, self::$privateKeyLocation);

            $buyer_request = array("display_name"=>"Tester T.");
            $result = $config->addBuyer($buyer_request);
            $this->assertArrayHasKey("id", $result);

            $embed = array("amount"=> 200, "currency" => "USD", "buyer_id"=> $result["id"]);
            $embedToken = $config->getEmbedToken($embed);
            $this->assertGreaterThan(0, strlen($embedToken), "Expected length to be greater than 0.");

            $result = $config->deleteBuyer($result["id"]);
            $this->assertArrayHasKey("success", $result);

        } catch (Exception $e) {
            $this->fail("Exception thrown: " . $e->getMessage());
        }
    }

}
