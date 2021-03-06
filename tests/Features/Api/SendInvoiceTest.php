<?php

/**
 * @package     Qodehub\Slydepay
 * @link        https://github.com/qodehub/slydepay-php
 *
 * @author      Ariama O. Victor (ovac4u) <victorariama@qodehub.com>
 * @link        http://www.ovac4u.com
 *
 * @license     https://github.com/qodehub/slydepay-php/blob/master/LICENSE
 * @copyright   (c) 2018, QodeHub, Ltd
 */

namespace Qodehub\SlydePay\Tests\Features\Api;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Qodehub\Slydepay\Api\SendInvoice;
use Qodehub\SlydePay\Config;
use Qodehub\Slydepay\Exception\MissingParameterException;
use Qodehub\SlydePay\SlydePay;
use Qodehub\SlydePay\Utility\SlydePayHandler;

class SendInvoiceTest extends TestCase
{
    /**
     * The configuration instance.
     * @var Config
     */
    protected $config;

    /**
     * This Package Version.
     *
     * @var string
     */
    protected $version = '1.0.0';
    /**
     * This will be the Authorization merchantKey
     *
     * @var string
     */
    protected $merchantKey = 'some-valid-merchantKey';
    /**
     * This is the emailOrMobileNumber for the Slydepay Merchant
     *
     * @var string
     */
    protected $emailOrMobileNumber = 1234567890;

    /**
     * Setup the test environment viriables
     * @return [type] [description]
     */
    public function setup()
    {
        $this->config = new Config($this->emailOrMobileNumber, $this->merchantKey);
    }

    /** @test */
    public function a_call_to_the_run_method_should_return_an_error_if_some_parameters_are_missing()
    {
        /**
         * Mock the execute method in the Addresses to intercept calls to the server
         */
        $mock = $this->getMockBuilder(SendInvoice::class)
            ->setMethods(['execute'])
            ->getMock();

        $mock->method('execute')->will($this->returnValue(null));

        $this->expectException(MissingParameterException::class);
        $mock->run();
    }

    /** @test */
    public function there_should_be_a_working_optional_externalAccountRef()
    {
        /**
         * Mock the execute method in the Addresses to intercept calls to the server
         */
        $mock = $this->getMockBuilder(SendInvoice::class)
            ->setMethods(['execute'])
            ->getMock();

        $mock->payoption('mm')
            ->paytoken('mm')
            ->customerName('Victor')
            ->customerEmail('iamovac@gmail.com')
            ->customerMobileNumber(12345)
            ->injectConfig($this->config);

        $mock->method('execute')->will($this->returnValue(null));
        $mock->run();

        $mock2 = $mock->setExternalAccountRef('external-ref-data');
        $mock2->run();

        $this->assertSame('external-ref-data', $mock2->getExternalAccountRef());
    }

    /** @test */
    public function test_that_a_call_to_the_server_will_be_successful_if_all_is_right()
    {
        /**
         * Setup the Handler and middlewares interceptor to intercept the call to the server
         */
        $container = [];

        $history = Middleware::history($container);

        $httpMock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], json_encode(['X-Foo' => 'Bar'])),
        ]);

        $handlerStack = (new SlydePayHandler($this->config, HandlerStack::create($httpMock)))->createHandler();

        $handlerStack->push($history);

        /**
         * Listen to the Addresses class method and use the interceptor
         *
         * Intercept all calls to the server from the createHandler method
         */
        $mock = $this->getMockBuilder(SendInvoice::class)
            ->setMethods(['createHandler'])
            ->getMock();

        $mock->expects($this->once())->method('createHandler')->will($this->returnValue($handlerStack));

        /**
         * Inject the configuration and use the
         */
        $mock
            ->payoption('mm')
            ->paytoken('mm')
            ->customerName('Victor')
            ->customerEmail('iamovac@gmail.com')
            ->customerMobileNumber(12345)
            ->injectConfig($this->config);

        /**
         * Run the call to the server
         */
        $result = $mock->run();

        /**
         * Run assertion that call reached the Mock Server
         */
        $this->assertEquals($result, json_decode(json_encode(['X-Foo' => 'Bar'])));

        /**
         * Grab the requests and test that the request parameters
         * are correct as expected.
         */
        $request = $container[0]['request'];

        $this->assertEquals($request->getMethod(), 'POST', 'it should be a post request.');
        $this->assertEquals($request->getUri()->getHost(), 'app.slydepay.com.gh', 'Hostname should be app.slydepay.com.gh');
        $this->assertEquals($request->getHeaderLine('User-Agent'), SlydePay::CLIENT . ' v' . SlydePay::VERSION);

        $this->assertEquals($request->getUri()->getScheme(), 'https', 'it should be a https scheme');

        $this->assertContains(
            "https://app.slydepay.com.gh/api/merchant/invoice/send",
            $request->getUri()->__toString()
        );
    }
}
