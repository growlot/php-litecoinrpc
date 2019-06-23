# Simple Litecoin JSON-RPC client based on GuzzleHttp

## About
This project is based on [php-litecoinrpc](https://github.com/majestic84/php-litecoinrpc) project - fully unit-tested Litecoin JSON-RPC client powered by GuzzleHttp.

## Installation
Run ```php composer.phar require growlot/php-litecoinrpc``` in your project directory or add following lines to composer.json
```javascript
"require": {
    "growlot/php-litecoinrpc": "^2.0"
}
```
and run ```php composer.phar update```.

## Requirements
PHP 7.0 or higher (should also work on 5.6, but this is unsupported)

## Usage
Create new object with url as parameter
```php
use Growlot\Litecoin\Client as LitecoinClient;

$litecoind = new LitecoinClient('http://rpcuser:rpcpassword@localhost:9332/');
```
or use array to define your litecoind settings
```php
use Growlot\Litecoin\Client as LitecoinClient;

$litecoind = new LitecoinClient([
    'scheme' => 'http',                 // optional, default http
    'host'   => 'localhost',            // optional, default localhost
    'port'   => 9332,                   // optional, default 9332
    'user'   => 'rpcuser',              // required
    'pass'   => 'rpcpassword',          // required
    'ca'     => '/etc/ssl/ca-cert.pem'  // optional, for use with https scheme
]);
```
Then call methods defined in [Litecoin Core API Documentation](https://litecoin.info/index.php/Litecoin_API) with magic:
```php
/**
 * Get block info.
 */
$block = $litecoind->getBlock('9d4d9fd2f4dee46d5918861b7bbff81f52c581c3b935ad186fe4c5b6dc58d2f8');

$block('hash')->get();     // 9d4d9fd2f4dee46d5918861b7bbff81f52c581c3b935ad186fe4c5b6dc58d2f8
$block['height'];          // 1298009 (array access)
$block->get('tx.0');       // a8971eaf8dfda3ee5dd20b3de3fb6c22e936339bbb53f8fa0f2379941ac5ff3f
$block->count('tx');       // 26
$block->has('version');    // key must exist and CAN NOT be null
$block->exists('version'); // key must exist and CAN be null
$block->contains(0);       // check if response contains value
$block->values();          // array of values
$block->keys();            // array of keys
$block->random(1, 'tx');   // random block txid
$block('tx')->random(2);   // two random block txid's
$block('tx')->first();     // txid of first transaction
$block('tx')->last();      // txid of last transaction

/**
 * Send transaction.
 */
$result = $litecoind->sendToAddress('LKdsQGCwBbgJNdXSQtAvVbFMpwgwThtsSY', 0.1);
$txid = $result->get();

/**
 * Get transaction amount.
 */
$result = $litecoind->listSinceBlock();
$totalAmount = $result->sum('transactions.*.amount');
$totalSatoshi = LitecoinClient::toSatoshi($totalAmount);
```
To send asynchronous request, add Async to method name:
```php
use Growlot\Litecoin\LitecoindResponse;

$promise = $litecoind->getBlockAsync(
    '9d4d9fd2f4dee46d5918861b7bbff81f52c581c3b935ad186fe4c5b6dc58d2f8',
    function (LitecoindResponse $success) {
        //
    },
    function (\Exception $exception) {
        //
    }
);

$promise->wait();
```

You can also send requests using request method:
```php
/**
 * Get block info.
 */
$block = $litecoind->request('getBlock', '9d4d9fd2f4dee46d5918861b7bbff81f52c581c3b935ad186fe4c5b6dc58d2f8');

$block('hash');            // 9d4d9fd2f4dee46d5918861b7bbff81f52c581c3b935ad186fe4c5b6dc58d2f8
$block['height'];          // 1298009 (array access)
$block->get('tx.0');       // a8971eaf8dfda3ee5dd20b3de3fb6c22e936339bbb53f8fa0f2379941ac5ff3f
$block->count('tx');       // 26
$block->has('version');    // key must exist and CAN NOT be null
$block->exists('version'); // key must exist and CAN be null
$block->contains(0);       // check if response contains value
$block->values();          // get response values
$block->keys();            // get response keys
$block->random(1, 'tx');   // get random txid

/**
 * Send transaction.
 */
$result = $litecoind->request('sendtoaddress', ['LKdsQGCwBbgJNdXSQtAvVbFMpwgwThtsSY', 0.06]);
$txid = $result->get();

```
or requestAsync method for asynchronous calls:
```php
use Growlot\Litecoin\LitecoindResponse;

$promise = $litecoind->requestAsync(
    'getBlock',
    '9d4d9fd2f4dee46d5918861b7bbff81f52c581c3b935ad186fe4c5b6dc58d2f8',
    function (LitecoindResponse $success) {
        //
    },
    function (\Exception $exception) {
        //
    }
);

$promise->wait();
```

## Multi-Wallet RPC
You can use `wallet($name)` function to do a [Multi-Wallet RPC call](https://github.com/litecoin-project/litecoin/blob/v0.15.0.1rc1/doc/release-notes-litecoin.md#multi-wallet-support):
```php
/**
 * Get wallet2.dat balance.
 */
$balance = $litecoind->wallet('wallet2.dat')->getbalance();

$balance->get(); // 0.10000000
```

## License

This product is distributed under MIT license.

## Donations

If you like this project,
you can donate Litecoins to LbKRh7icJy8En3MDcPsxhLhF9quH9VNrgS.

Thanks for your support!
