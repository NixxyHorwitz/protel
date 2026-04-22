<?php declare(strict_types=1);

use Amp\CancelledException;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;

require __DIR__ . '/../.helper/functions.php';

try {
    $pool = new UnlimitedConnectionPool();

    $clientA = (new HttpClientBuilder)
        ->usingPool($pool)
        ->followRedirects(0) // no follow
        ->retry(3)
        ->build();

    $clientB = (new HttpClientBuilder)
        ->usingPool($pool)
        ->followRedirects() // follow
        ->retry(3)
        ->build();

    $responseA = $clientA->request(new Request($argv[1] ?? 'https://httpbin.org/redirect-to?url=%2f'));
    $responseB = $clientB->request(new Request($argv[1] ?? 'https://httpbin.org/redirect-to?url=%2f'));

    dumpResponseTrace($responseA);
    dumpResponseTrace($responseB);
} catch (HttpException|CancelledException $error) {
    echo $error;
}
