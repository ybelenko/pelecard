<?php
/**
 * @file PelecardHttpRequestTest class - tests PelecardHttpRequest class.
 */
namespace Pelecard;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Pelecard\PelecardHttpRequest;

class PelecardHttpRequestTest extends TestCase {

  /**
   * Tests getConfig static method with wrong $clientConfig types
   *
   * @dataProvider provideWrongConfig
   * @covers Pelecard\PelecardHttpRequest::getConfig
   */
  public function testGetConfigWithWrongConfig($config) {
    // default config
    $defaults = [
      'base_uri' => PelecardHttpRequest::GATEWAY_BASE_URI,
      'headers' => ['Content-Type' => 'application/json'],
      'connect_timeout' => PelecardHttpRequest::CONNECTION_TIMEOUT,
      'timeout' => PelecardHttpRequest::REQUEST_TIMEOUT,
    ];

    $beforeConfig = PelecardHttpRequest::getConfig();
    $this->assertJsonStringEqualsJsonString(json_encode($defaults), json_encode($beforeConfig));

    // check that provided config is invalid because not array
    $this->assertFalse(is_array($config));

    // overwrite config with user values
    PelecardHttpRequest::$clientConfig = $config;
    $afterConfig = PelecardHttpRequest::getConfig();

    // check that overwritten config didn't change
    $this->assertJsonStringEqualsJsonString(json_encode($defaults), json_encode($afterConfig));
  }

  /**
   * Tests getConfig static method with correct $clientConfig data
   *
   * @dataProvider provideCorrectConfig
   * @covers Pelecard\PelecardHttpRequest::getConfig
   */
  public function testGetConfigWithCorrectConfig($config) {
    // default config
    $defaults = [
      'base_uri' => PelecardHttpRequest::GATEWAY_BASE_URI,
      'headers' => ['Content-Type' => 'application/json'],
      'connect_timeout' => PelecardHttpRequest::CONNECTION_TIMEOUT,
      'timeout' => PelecardHttpRequest::REQUEST_TIMEOUT,
    ];

    $beforeConfig = PelecardHttpRequest::getConfig();
    $this->assertJsonStringEqualsJsonString(json_encode($defaults), json_encode($beforeConfig));

    // check that provided config is valid
    $this->assertTrue(is_array($config));

    // overwrite config with user values
    PelecardHttpRequest::$clientConfig = $config;
    $afterConfig = PelecardHttpRequest::getConfig();

    // check that overwritten config did change
    $this->assertJsonStringNotEqualsJsonString(json_encode($defaults), json_encode($afterConfig));

    // check static values
    $this->assertArrayHasKey('base_uri', $afterConfig);
    $this->assertArrayHasKey('headers', $afterConfig);
    $this->assertEquals(PelecardHttpRequest::GATEWAY_BASE_URI, $afterConfig['base_uri']);
    $this->assertEquals(['Content-Type' => 'application/json'], $afterConfig['headers']);

    // check user values
    foreach ($config as $optionName => $optionValue) {
      if (!in_array($optionName, ['base_uri', 'headers'])) {
        // value should be overwritten
        $this->assertArrayHasKey($optionName, $afterConfig);
        $this->assertEquals($optionValue, $afterConfig[$optionName]);
      }
    }
  }

  /**
   * Tests pelecardPostRequest static method sends JSON POST and returns body
   *
   * @covers Pelecard\PelecardHttpRequest::pelecardPostRequest
   */
  public function testPelecardPostRequest() {
    $history = [];
    $handlerStack = HandlerStack::create(new MockHandler([
      new Response(200, ['Content-Type' => 'application/json'], '{"Error":{"ErrCode":0}}'),
    ]));
    $handlerStack->push(Middleware::history($history));

    PelecardHttpRequest::$clientConfig = ['handler' => $handlerStack];
    try {
      $result = PelecardHttpRequest::pelecardPostRequest('PaymentGW/init', ['terminal' => '0123456', 'total' => 100]);
    } finally {
      PelecardHttpRequest::$clientConfig = null;
    }

    $this->assertSame('{"Error":{"ErrCode":0}}', $result);
    $this->assertCount(1, $history);
    $request = $history[0]['request'];
    $this->assertSame('POST', $request->getMethod());
    $this->assertSame(PelecardHttpRequest::GATEWAY_BASE_URI . '/PaymentGW/init', (string) $request->getUri());
    $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
    $this->assertJsonStringEqualsJsonString('{"terminal":"0123456","total":100}', (string) $request->getBody());
    $this->assertSame(PelecardHttpRequest::CONNECTION_TIMEOUT, $history[0]['options']['connect_timeout']);
    $this->assertSame(PelecardHttpRequest::REQUEST_TIMEOUT, $history[0]['options']['timeout']);
  }

  public function provideWrongConfig() {
    return [
      [null],
      [1],
      ['foo'],
      ['bar'],
      [new \DateTime()],
      [new \StdClass()],
    ];
  }

  public function provideCorrectConfig() {
    return [
      [
        [
          'connect_timeout' => 50,
          'timeout' => 50,
          'base_uri' => 'newbase/api',
          'headers' => ['Content-Type' => 'application/json'],
        ],
      ],
    ];
  }
}
