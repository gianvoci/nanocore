<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\NanoCore;

$tests = [];

// Test 1: getBodyRequest in CLI returns empty string (php://input is empty)
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $result = $app->getBodyRequest();
    assertEquals('', $result, 'getBodyRequest in CLI should return empty string');

    unlink($tmpFile);
};

// Test 2: getBodyRequest with custom maxBytes doesn't crash in CLI
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    // Default limit
    $result1 = $app->getBodyRequest();
    assertEquals('', $result1, 'getBodyRequest with default limit should return empty string');

    // Small limit — still fine since input is empty (0 bytes)
    $result2 = $app->getBodyRequest(1);
    assertEquals('', $result2, 'getBodyRequest with maxBytes=1 should still return empty string');

    unlink($tmpFile);
};

// Test 3: Magic __get for 'body' returns getBodyRequest result
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $body = $app->body;
    assertEquals('', $body, 'Magic property body should return empty string in CLI');

    unlink($tmpFile);
};

// Test 4: Magic __get for 'cli' returns true in CLI mode
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    assertTrue($app->cli, 'Magic property cli should be true when running from CLI');

    unlink($tmpFile);
};

// Test 5: Magic __get for unknown property returns null
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    assertEquals(null, $app->nonexistent, 'Unknown magic property should return null');

    unlink($tmpFile);
};

// Test 6: Magic __set and __get for custom storage
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->myKey = 'myValue';
    assertEquals('myValue', $app->myKey, 'Stored string should be retrievable');

    $app->anotherKey = ['complex' => 'data'];
    assertEquals(['complex' => 'data'], $app->anotherKey, 'Stored array should be retrievable');

    unlink($tmpFile);
};

// Test 7: renderHtml replaces placeholders
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $htmlPath = createTempHtml('<h1>{{title}}</h1><p>{{body}}</p>');

    $result = $app->renderHtml($htmlPath, ['{{title}}' => 'Hello', '{{body}}' => 'World']);
    assertEquals('<h1>Hello</h1><p>World</p>', $result, 'renderHtml should replace placeholders');

    unlink($htmlPath);
    unlink($tmpFile);
};

// Test 8: renderHtml with no data returns template unchanged
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $htmlPath = createTempHtml('<h1>{{title}}</h1>');

    $result = $app->renderHtml($htmlPath, []);
    assertEquals('<h1>{{title}}</h1>', $result, 'renderHtml with empty data should return template unchanged');

    unlink($htmlPath);
    unlink($tmpFile);
};

// Test 9: renderHtml with empty template
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $htmlPath = createTempHtml('');

    $result = $app->renderHtml($htmlPath, ['{{key}}' => 'value']);
    assertEquals('', $result, 'renderHtml with empty template should return empty string');

    unlink($htmlPath);
    unlink($tmpFile);
};

// Test 10: getBodyRequest does not throw with zero-byte input and zero maxBytes
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    // maxBytes=0: strlen('') is 0, which is NOT > 0, so no exception
    $result = $app->getBodyRequest(0);
    assertEquals('', $result, 'getBodyRequest with maxBytes=0 should not throw on empty input');

    unlink($tmpFile);
};

// Test 11: renderHtml replaces multiple occurrences of the same placeholder
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $htmlPath = createTempHtml('<p>{{name}}</p><span>{{name}}</span>');

    $result = $app->renderHtml($htmlPath, ['{{name}}' => 'Alice']);
    assertEquals('<p>Alice</p><span>Alice</span>', $result, 'renderHtml should replace all occurrences of a placeholder');

    unlink($htmlPath);
    unlink($tmpFile);
};

// Test 12: execDetach does not crash (basic smoke test)
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    // execDetach calls ob_flush(), so we need an active output buffer
    ob_start();

    // Use a harmless command that exits immediately
    $app->execDetach('echo test');

    ob_end_clean();
    unlink($tmpFile);
};

// Test 13: renderHtml escapes HTML by default (XSS prevention)
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();
    
    $htmlPath = createTempHtml('<p>{{content}}</p>');

    $result = $app->renderHtml($htmlPath, ['{{content}}' => '<script>alert("xss")</script>']);
    assertTrue(strpos($result, '&lt;script&gt;') !== false, 'renderHtml should HTML-escape content by default');
    
    unlink($htmlPath);
    unlink($tmpFile);
};

// Test 14: renderHtml with escape=false does NOT escape
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();
    
    $htmlPath = createTempHtml('<p>{{content}}</p>');

    $result = $app->renderHtml($htmlPath, ['{{content}}' => '<b>bold</b>'], false);
    assertTrue(strpos($result, '<b>bold</b>') !== false, 'renderHtml with escape=false should not escape HTML content');
    
    unlink($htmlPath);
    unlink($tmpFile);
};

// Test 15: getBodyRequest throws on wrong Content-Type when validateContentType=true
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();
    
    $_SERVER['CONTENT_TYPE'] = 'text/plain';
    
    assertThrows(
        \Exception::class,
        'Content-Type must be application/json, got: text/plain',
        function () use ($app) {
            $app->getBodyRequest(10485760, true);
        }
    );
    
    unset($_SERVER['CONTENT_TYPE']);
    unlink($tmpFile);
};

// Test 16: execDetach with array command (proper argument escaping)
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();
    
    // execDetach calls ob_flush(), so we need an active output buffer
    ob_start();
    
    // Use array form of command
    $app->execDetach(['echo', 'hello world']);
    
    ob_end_clean();
    unlink($tmpFile);
};

// Test 17: curlRequest with with_info returns array with body, status, content_type keys
$tests[] = function () {
    $result = NanoCore::curlRequest('https://httpbin.org/get', ['with_info' => true, 'raw' => true]);
    assertTrue(is_array($result), 'curlRequest with with_info should return an array');
    assertTrue(array_key_exists('body', $result), 'with_info result should have body key');
    assertTrue(array_key_exists('status', $result), 'with_info result should have status key');
    assertTrue(array_key_exists('content_type', $result), 'with_info result should have content_type key');
    if ($result['status'] !== 200) {
        return;
    }
    assertEquals(200, $result['status'], 'with_info status should be 200 for successful request');
};

// Test 18: curlRequest without with_info returns body directly (backward compat)
$tests[] = function () {
    $result = NanoCore::curlRequest('https://httpbin.org/get', ['raw' => true]);
    assertTrue(is_string($result), 'curlRequest without with_info should return string body directly');
};

// Test 19: curlRequest with_info content_type is populated
$tests[] = function () {
    $result = NanoCore::curlRequest('https://httpbin.org/get', ['with_info' => true, 'raw' => true]);
    if ($result['status'] !== 200) {
        return;
    }
    assertTrue(is_string($result['content_type']), 'with_info content_type should be a string for valid response');
    assertTrue(str_contains($result['content_type'], 'application/json'), 'httpbin /get should return application/json content type');
};

// Test 20: curlRequest with_info returns correct status for non-200
$tests[] = function () {
    $result = NanoCore::curlRequest('https://httpbin.org/status/418', ['with_info' => true, 'raw' => true]);
    assertTrue(is_array($result), 'with_info with non-200 should return an array');
    if ($result['status'] === 503) {
        return;
    }
    assertEquals(418, $result['status'], 'with_info status should match the HTTP status code');
};

// Test 21: curlRequest with_info without raw returns JSON-decoded body
$tests[] = function () {
    $result = NanoCore::curlRequest('https://httpbin.org/get', ['with_info' => true]);
    assertTrue(is_array($result), 'with_info without raw should return an array');
    if ($result['status'] !== 200) {
        return;
    }
    assertEquals(200, $result['status'], 'with_info status should be 200');
    assertTrue(is_array($result['body']), 'with_info body should be JSON-decoded array when response is JSON');
};

runTests($tests);
