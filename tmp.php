<?php
require 'vendor/autoload.php';

use Anibalealvarezs\KlaviyoApi\KlaviyoApi;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;

$mock = new MockHandler([
    new Response(200, [], json_encode(['data' => []]))
]);
$handlerStack = HandlerStack::create($mock);
$guzzle = new GuzzleClient(['handler' => $handlerStack]);

$api = new KlaviyoApi('fake_key');
$api->setGuzzleClient($guzzle);

try {
    $api->getMetrics();
} catch (\Exception $e) {
    echo $e->getMessage();
}

$request = $mock->getLastRequest();
echo "HEADERS:\n";
foreach ($request->getHeaders() as $name => $values) {
    echo $name . ": " . implode(", ", $values) . "\n";
}
echo "URI: " . $request->getUri() . "\n";
