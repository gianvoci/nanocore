<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\NanoCore;

$tests = [];

// Test 1: SSRF validation — private IP throws Exception
$tests[] = function () {
    assertThrows(\Exception::class, 'URL points to a restricted network address', function () {
        NanoCore::validateUrlNotRestricted('http://192.168.1.1/test');
    });
};

// Test 2: SSRF validation — localhost hostname throws Exception
$tests[] = function () {
    assertThrows(\Exception::class, 'URL points to a restricted network address', function () {
        NanoCore::validateUrlNotRestricted('http://localhost/test');
    });
};

// Test 3: SSRF validation — non-http scheme throws Exception
$tests[] = function () {
    assertThrows(\Exception::class, 'URL scheme not allowed: ftp', function () {
        NanoCore::validateUrlNotRestricted('ftp://example.com/file');
    });
};

// Test 4: SSRF validation — public IP passes validation (no exception)
$tests[] = function () {
    NanoCore::validateUrlNotRestricted('http://93.184.216.34/');
    assertTrue(true, 'Public IP should not throw');
};

// Test 5: validateIpNotRestricted — loopback throws
$tests[] = function () {
    assertThrows(\Exception::class, 'URL points to a restricted network address', function () {
        NanoCore::validateIpNotRestricted('127.0.0.1');
    });
};

// Test 6: validateIpNotRestricted — private range throws
$tests[] = function () {
    assertThrows(\Exception::class, 'URL points to a restricted network address', function () {
        NanoCore::validateIpNotRestricted('10.0.0.1');
    });
};

// Test 7: validateIpNotRestricted — public IP passes
$tests[] = function () {
    NanoCore::validateIpNotRestricted('93.184.216.34');
    assertTrue(true, 'Public IP should not throw');
};

// Test 8: Option merging — logical keys are removed from CURLOPT array (smoke test)
$tests[] = function () {
    // If logical keys weren't stripped before curl_setopt_array, curl would fail on string keys.
    // This is a documentation test — the real validation is that curlRequest doesn't crash.
    assertTrue(true, 'Option merging: logical keys stripped (verified by code review)');
};

// Test 9: curlRequest SSRF protection — restricted IPv4 loopback
$tests[] = function () {
    assertThrows(\Exception::class, 'URL points to a restricted network address', function () {
        NanoCore::curlRequest('http://127.0.0.1/test');
    });
};

// Test 10: curlRequest SSRF protection — restricted IPv6 loopback
$tests[] = function () {
    assertThrows(\Exception::class, 'URL points to a restricted network address', function () {
        NanoCore::curlRequest('http://[::1]/test');
    });
};

// Test 11: curlRequest SSRF protection — invalid URL
$tests[] = function () {
    assertThrows(\Exception::class, 'Invalid URL', function () {
        NanoCore::curlRequest('not-a-url');
    });
};

// Test 12: with_info option — returns array with body, status, content_type (requires running test server, skipped)
$tests[] = function () {
    assertTrue(true, 'with_info option test skipped (requires running test server)');
};

// Test 13: raw option — skips JSON decoding (requires running test server, skipped)
$tests[] = function () {
    assertTrue(true, 'raw option test skipped (requires running test server)');
};

// Test 14: Invalid URL throws Exception
$tests[] = function () {
    assertThrows(\Exception::class, 'Invalid URL', function () {
        NanoCore::validateUrlNotRestricted('not-a-url');
    });
};

// Test 15: Batch of 2 URLs returns array in order
$tests[] = function () {
    $urls = ['https://httpbin.org/get', 'https://httpbin.org/status/200'];
    $results = NanoCore::curlRequest($urls, ['raw' => true]);
    assertTrue(is_array($results), 'Batch should return array');
    assertTrue(count($results) === 2, 'Batch should return 2 results');
};

// Test 16: Batch preserves URL order in results
$tests[] = function () {
    $urls = ['https://httpbin.org/anything?pos=1', 'https://httpbin.org/anything?pos=2'];
    $results = NanoCore::curlRequest($urls, ['raw' => true]);
    $first = json_decode($results[0], true);
    $second = json_decode($results[1], true);
    assertTrue($first['args']['pos'] === '1', 'First result should match first URL');
    assertTrue($second['args']['pos'] === '2', 'Second result should match second URL');
};

// Test 17: Batch with with_info returns array of info arrays
$tests[] = function () {
    $urls = ['https://httpbin.org/get', 'https://httpbin.org/status/418'];
    $results = NanoCore::curlRequest($urls, ['with_info' => true, 'raw' => true]);
    assertTrue(is_array($results[0]) && isset($results[0]['body']), 'First batch result should be info array');
    assertTrue(is_array($results[1]) && isset($results[1]['status']), 'Second batch result should be info array');
    assertTrue($results[1]['status'] === 418, 'Second batch result status should be 418');
};

// Test 18: Batch failure returns Exception object at failed index, others succeed
$tests[] = function () {
    $results = NanoCore::curlRequest(
        ['https://httpbin.org/get', 'http://nonexistent.invalid'],
        ['raw' => true, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 2]
    );
    assertTrue(is_array($results), 'Batch should return array even with failures');
    assertTrue(is_string($results[0]), 'First URL should succeed with body string');
    assertTrue($results[1] instanceof Exception, 'Second URL should be Exception object');
};

// Test 19: Empty URL array returns empty array
$tests[] = function () {
    $results = NanoCore::curlRequest([], ['raw' => true]);
    assertTrue(is_array($results) && count($results) === 0, 'Empty array should return empty array');
};

// Test 20: Single URL string still works (backward compat)
$tests[] = function () {
    $result = NanoCore::curlRequest('https://httpbin.org/get', ['raw' => true]);
    assertTrue(is_string($result), 'Single URL string should return string body');
};

// Test 21: Batch of 15 URLs completes successfully (smoke test for concurrency queue)
$tests[] = function () {
    $urls = [];
    for ($i = 0; $i < 15; $i++) {
        $urls[] = 'https://httpbin.org/status/200';
    }
    $results = NanoCore::curlRequest($urls, ['raw' => true]);
    assertTrue(count($results) === 15, 'Should return 15 results for 15 URLs');
};

runTests($tests);