<?php

use Growlot\Litecoin;
use Growlot\Litecoin\Exceptions;
use GuzzleHttp\Psr7\Response;

class ClientTest extends TestCase
{
    /**
     * Sets up test.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->litecoind = new Litecoin\Client();
    }

    /**
     * Tests url parser.
     *
     * @param string $url
     * @param string $scheme
     * @param string $host
     * @param int    $port
     * @param string $user
     * @param string $pass
     *
     * @return void
     *
     * @dataProvider urlProvider
     */
    public function testUrlParser($url, $scheme, $host, $port, $user, $pass)
    {
        $litecoind = new Litecoin\Client($url);

        $this->assertInstanceOf(Litecoin\Client::class, $litecoind);

        $base_uri = $litecoind->getConfig('base_uri');

        $this->assertEquals($base_uri->getScheme(), $scheme);
        $this->assertEquals($base_uri->getHost(), $host);
        $this->assertEquals($base_uri->getPort(), $port);

        $auth = $litecoind->getConfig('auth');
        $this->assertEquals($auth[0], $user);
        $this->assertEquals($auth[1], $pass);
    }

    /**
     * Data provider for url expander test.
     *
     * @return array
     */
    public function urlProvider()
    {
        return [
            ['https://localhost', 'https', 'localhost', 9332, '', ''],
            ['https://localhost:9000', 'https', 'localhost', 9000, '', ''],
            ['http://localhost', 'http', 'localhost', 9332, '', ''],
            ['http://localhost:9000', 'http', 'localhost', 9000, '', ''],
            ['http://testuser@127.0.0.1:9000/', 'http', '127.0.0.1', 9000, 'testuser', ''],
            ['http://testuser:testpass@localhost:9000', 'http', 'localhost', 9000, 'testuser', 'testpass'],
        ];
    }

    /**
     * Tests url parser with invalid url.
     *
     * @return void
     */
    public function testUrlParserWithInvalidUrl()
    {
        try {
            $litecoind = new Litecoin\Client('cookies!');

            $this->expectException(Exceptions\ClientException::class);
        } catch (Exceptions\ClientException $e) {
            $this->assertEquals('Invalid url', $e->getMessage());
        }
    }

    /**
     * Tests client getter and setter.
     *
     * @return void
     */
    public function testClientSetterGetter()
    {
        $litecoind = new Litecoin\Client('http://old_client.org');
        $this->assertInstanceOf(Litecoin\Client::class, $litecoind);

        $base_uri = $litecoind->getConfig('base_uri');
        $this->assertEquals($base_uri->getHost(), 'old_client.org');

        $oldClient = $litecoind->getClient();
        $this->assertInstanceOf(\GuzzleHttp\Client::class, $oldClient);

        $newClient = new \GuzzleHttp\Client(['base_uri' => 'http://new_client.org']);
        $litecoind->setClient($newClient);

        $base_uri = $litecoind->getConfig('base_uri');
        $this->assertEquals($base_uri->getHost(), 'new_client.org');
    }

    /**
     * Tests ca config option.
     *
     * @return void
     */
    public function testCaOption()
    {
        $litecoind = new Litecoin\Client();

        $this->assertEquals(null, $litecoind->getConfig('ca'));

        $litecoind = new Litecoin\Client([
            'ca' => __FILE__,
        ]);

        $this->assertEquals(__FILE__, $litecoind->getConfig('verify'));
    }

    /**
     * Tests simple request.
     *
     * @return void
     */
    public function testRequest()
    {
        $guzzle = $this->mockGuzzle([
            $this->getBlockResponse(),
        ]);

        $response = $this->litecoind
            ->setClient($guzzle)
            ->request(
                'getblockheader',
                '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f'
            );

        $this->assertEquals(self::$getBlockResponse, $response->get());
    }

    /**
     * Tests multiwallet request.
     *
     * @return void
     */
    public function testMultiWalletRequest()
    {
        $wallet = 'testwallet.dat';
        $history = [];

        $guzzle = $this->mockGuzzle([
            $this->getBalanceResponse(),
        ], $history);

        $response = $this->litecoind
            ->setClient($guzzle)
            ->wallet($wallet)
            ->request('getbalance');

        $request = $history[0]['request'];
        $this->assertEquals(self::$balanceResponse, $response->get());
        $this->assertEquals($request->getUri()->getPath(), "/wallet/$wallet");
    }

    /**
     * Tests async multiwallet request.
     *
     * @return void
     */
    public function testMultiWalletAsyncRequest()
    {
        $wallet = 'testwallet2.dat';
        $history = [];

        $guzzle = $this->mockGuzzle([
            $this->getBalanceResponse(),
        ], $history);

        $onFulfilled = $this->mockCallable([
            $this->callback(function (Litecoin\LitecoindResponse $response) {
                return $response->get() == self::$balanceResponse;
            }),
        ]);

        $promise = $this->litecoind
            ->setClient($guzzle)
            ->wallet($wallet)
            ->requestAsync(
                'getbalance',
                [],
                function ($response) use ($onFulfilled) {
                    $onFulfilled($response);
                }
            );

        $promise->wait();

        $request = $history[0]['request'];
        $this->assertEquals($request->getUri()->getPath(), "/wallet/$wallet");
    }

    /**
     * Tests async request.
     *
     * @return void
     */
    public function testAsyncRequest()
    {
        $guzzle = $this->mockGuzzle([
            $this->getBlockResponse(),
        ]);

        $onFulfilled = $this->mockCallable([
            $this->callback(function (Litecoin\LitecoindResponse $response) {
                return $response->get() == self::$getBlockResponse;
            }),
        ]);

        $promise = $this->litecoind
            ->setClient($guzzle)
            ->requestAsync(
                'getblockheader',
                '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f',
                function ($response) use ($onFulfilled) {
                    $onFulfilled($response);
                }
            );

        $promise->wait();
    }

    /**
     * Tests magic request.
     *
     * @return void
     */
    public function testMagic()
    {
        $guzzle = $this->mockGuzzle([
            $this->getBlockResponse(),
        ]);

        $response = $this->litecoind
            ->setClient($guzzle)
            ->getBlockHeader(
                '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f'
            );

        $this->assertEquals(self::$getBlockResponse, $response->get());
    }

    /**
     * Tests magic request.
     *
     * @return void
     */
    public function testAsyncMagic()
    {
        $guzzle = $this->mockGuzzle([
            $this->getBlockResponse(),
        ]);

        $onFulfilled = $this->mockCallable([
            $this->callback(function (Litecoin\LitecoindResponse $response) {
                return $response->get() == self::$getBlockResponse;
            }),
        ]);

        $promise = $this->litecoind
            ->setClient($guzzle)
            ->getBlockHeaderAsync(
                '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f',
                function ($response) use ($onFulfilled) {
                    $onFulfilled($response);
                }
            );

        $promise->wait();
    }

    /**
     * Tests litecoind exception.
     *
     * @return void
     */
    public function testLitecoindException()
    {
        $guzzle = $this->mockGuzzle([
            $this->rawTransactionError(200),
        ]);

        try {
            $response = $this->litecoind
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\LitecoindException::class);
        } catch (Exceptions\LitecoindException $e) {
            $this->assertEquals(self::$rawTransactionError['message'], $e->getMessage());
            $this->assertEquals(self::$rawTransactionError['code'], $e->getCode());
        }
    }

    /**
     * Tests async litecoind exception.
     *
     * @return void
     */
    public function testAsyncLitecoindException()
    {
        $guzzle = $this->mockGuzzle([
            $this->rawTransactionError(200),
        ]);

        $onFulfilled = $this->mockCallable([
            $this->callback(function (Exceptions\LitecoindException $exception) {
                return $exception->getMessage() == self::$rawTransactionError['message'] &&
                    $exception->getCode() == self::$rawTransactionError['code'];
            }),
        ]);

        $promise = $this->litecoind
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                function ($response) use ($onFulfilled) {
                    $onFulfilled($response);
                }
            );

        $promise->wait();
    }

    /**
     * Tests request exception with error code.
     *
     * @return void
     */
    public function testRequestExceptionWithServerErrorCode()
    {
        $guzzle = $this->mockGuzzle([
            $this->rawTransactionError(500),
        ]);

        try {
            $this->litecoind
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\LitecoindException::class);
        } catch (Exceptions\LitecoindException $exception) {
            $this->assertEquals(
                self::$rawTransactionError['message'],
                $exception->getMessage()
            );
            $this->assertEquals(
                self::$rawTransactionError['code'],
                $exception->getCode()
            );
        }
    }

    /**
     * Tests async request exception with error code.
     *
     * @return void
     */
    public function testAsyncRequestExceptionWithServerErrorCode()
    {
        $guzzle = $this->mockGuzzle([
            $this->rawTransactionError(500),
        ]);

        $onRejected = $this->mockCallable([
            $this->callback(function (Exceptions\LitecoindException $exception) {
                return $exception->getMessage() == self::$rawTransactionError['message'] &&
                    $exception->getCode() == self::$rawTransactionError['code'];
            }),
        ]);

        $promise = $this->litecoind
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                null,
                function ($exception) use ($onRejected) {
                    $onRejected($exception);
                }
            );

        $promise->wait(false);
    }

    /**
     * Tests request exception with empty response body.
     *
     * @return void
     */
    public function testRequestExceptionWithEmptyResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            new Response(500),
        ]);

        try {
            $response = $this->litecoind
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\ClientException::class);
        } catch (Exceptions\ClientException $exception) {
            $this->assertEquals(
                $this->error500(),
                $exception->getMessage()
            );
            $this->assertEquals(500, $exception->getCode());
        }
    }

    /**
     * Tests async request exception with empty response body.
     *
     * @return void
     */
    public function testAsyncRequestExceptionWithEmptyResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            new Response(500),
        ]);

        $onRejected = $this->mockCallable([
            $this->callback(function (Exceptions\ClientException $exception) {
                return $exception->getMessage() == $this->error500() &&
                    $exception->getCode() == 500;
            }),
        ]);

        $promise = $this->litecoind
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                null,
                function ($exception) use ($onRejected) {
                    $onRejected($exception);
                }
            );

        $promise->wait(false);
    }

    /**
     * Tests request exception with response.
     *
     * @return void
     */
    public function testRequestExceptionWithResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            $this->requestExceptionWithResponse(),
        ]);

        try {
            $response = $this->litecoind
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\LitecoindException::class);
        } catch (Exceptions\LitecoindException $exception) {
            $this->assertEquals(
                self::$rawTransactionError['message'],
                $exception->getMessage()
            );
            $this->assertEquals(
                self::$rawTransactionError['code'],
                $exception->getCode()
            );
        }
    }

    /**
     * Tests async request exception with response.
     *
     * @expectedException GuzzleHttp\Exception\RequestException
     *
     * @return void
     */
    public function testAsyncRequestExceptionWithResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            $this->requestExceptionWithResponse(),
        ]);

        $onRejected = $this->mockCallable([
            $this->callback(function (Exceptions\LitecoindException $exception) {
                return $exception->getMessage() == self::$rawTransactionError['message'] &&
                    $exception->getCode() == self::$rawTransactionError['code'];
            }),
        ]);

        $promise = $this->litecoind
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                null,
                function ($exception) use ($onRejected) {
                    $onRejected($exception);
                }
            );

        $promise->wait();
    }

    /**
     * Tests request exception with no response.
     *
     * @return void
     */
    public function testRequestExceptionWithNoResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            $this->requestExceptionWithoutResponse(),
        ]);

        try {
            $response = $this->litecoind
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\ClientException::class);
        } catch (Exceptions\ClientException $exception) {
            $this->assertEquals(
                'test',
                $exception->getMessage()
            );
            $this->assertEquals(0, $exception->getCode());
        }
    }

    /**
     * Tests async request exception with no response.
     *
     * @expectedException GuzzleHttp\Exception\RequestException
     *
     * @return void
     */
    public function testAsyncRequestExceptionWithNoResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            $this->requestExceptionWithoutResponse(),
        ]);

        $onRejected = $this->mockCallable([
            $this->callback(function (Exceptions\ClientException $exception) {
                return $exception->getMessage() == 'test' &&
                    $exception->getCode() == 0;
            }),
        ]);

        $promise = $this->litecoind
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                null,
                function ($exception) use ($onRejected) {
                    $onRejected($exception);
                }
            );

        $promise->wait();
    }

    /**
     * Tests conversion of Satoshi to LTC.
     *
     * @return void
     */
    public function testToLtc()
    {
        $this->assertEquals(0.00005849, Litecoin\Client::toLtc(5849));
    }

    /**
     * Tests conversion of LTC to Satoshi.
     *
     * @return void
     */
    public function testToSatoshi()
    {
        $this->assertEquals(5849, Litecoin\Client::toSatoshi(0.00005849));
    }

    /**
     * Tests precision of float number.
     *
     * @return void
     */
    public function testToFixed()
    {
        $this->assertSame('1', Litecoin\Client::toFixed(1.2345678910, 0));
        $this->assertSame('1.23', Litecoin\Client::toFixed(1.2345678910, 2));
        $this->assertSame('1.2345', Litecoin\Client::toFixed(1.2345678910, 4));
        $this->assertSame('1.23456789', Litecoin\Client::toFixed(1.2345678910, 8));
    }
}
