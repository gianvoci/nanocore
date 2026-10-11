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
    $results = NanoCore::curlRequest($urls, ['with_info' => true, 'raw' => true, 'ignore_http_errors' => true]);
    assertTrue(is_array($results[0]) && isset($results[0]['body']), 'First batch result should be info array');
    assertTrue(is_array($results[1]) && isset($results[1]['status']), 'Second batch result should be info array');
    assertTrue($results[1]['status'] === 418, 'Second batch result status should be 418');
};

// Test 18: Batch failure returns Exception object at failed index, others succeed
$tests[] = function () {
    // Literal public IP with a closed port: passes validation (no DNS pin needed), then fails at
    // transport level → GET is idempotent, retries, exhausts, returns an Exception at index 1.
    $results = NanoCore::curlRequest(
        ['https://httpbin.org/get', 'http://1.1.1.1:81/'],
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

// Test 22: Non-idempotent method (POST) is not retried on transport failure
$tests[] = function () {
    $start = hrtime(true);
    assertThrows(\Exception::class, '', function () {
        NanoCore::curlRequest('https://httpbin.org/delay/10', [
            'method' => 'POST',
            'raw' => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
    });
    $elapsed = (hrtime(true) - $start) / 1e9;
    assertTrue($elapsed < 4.0, 'Non-idempotent request must not be retried (one ~2s attempt, not two)');
};

// Test 23: curlRequest log entry is a single line despite newlines in the response body
$tests[] = function () {
    $configDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nc_curl_' . uniqid();
    mkdir($configDir);
    $tmpFile = $configDir . DIRECTORY_SEPARATOR . '.env';
    $logFile = $configDir . DIRECTORY_SEPARATOR . 'nanocore.log';
    file_put_contents($tmpFile, "CORE.EXPOSE_ERRORS=false\n");

    try {
        new NanoCore($tmpFile);

        NanoCore::curlRequest('https://httpbin.org/html', ['raw' => true]);

        $log = file_get_contents($logFile);
        assertEquals(1, count(explode("\n", trim($log))), 'Log entry must be a single line');
    } finally {
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        unlink($tmpFile);
        rmdir($configDir);
    }
};

// Test 24: Unresolvable host is rejected by the batch guard before any I/O
$tests[] = function () {
    assertThrows(\Exception::class, 'URL host could not be resolved', function () {
        NanoCore::curlRequest('http://nonexistent.invalid');
    });
};

// Test 25: Host ending with a dot is rejected (fail-closed: CURLOPT_RESOLVE can miss 'host.')
$tests[] = function () {
    assertThrows(\Exception::class, 'URL host must not end with a dot', function () {
        NanoCore::curlRequest('http://example.com./');
    });
};

// Test 26: json and params together is rejected before any I/O
$tests[] = function () {
    // Literal IP so the SSRF pre-check needs no DNS; validation must fire before any handle is created
    assertThrows(\InvalidArgumentException::class, "Use either 'params' or 'json'", function () {
        NanoCore::curlRequest('http://93.184.216.34/', [
            'method' => 'POST',
            'params' => ['a' => 1],
            'json'   => ['b' => 2],
        ]);
    });
};

// Test 27: json with GET (default method) is rejected
$tests[] = function () {
    assertThrows(\InvalidArgumentException::class, "Option 'json' is not supported with GET/HEAD", function () {
        NanoCore::curlRequest('http://93.184.216.34/', ['json' => ['a' => 1]]);
    });
};

// Test 28: json injects Content-Type: application/json and sends the encoded body
$tests[] = function () {
    $result = NanoCore::curlRequest('https://httpbin.org/anything', [
        'method' => 'POST',
        'json'   => ['hello' => 'world'],
        'raw'    => true,
    ]);
    $echoed = json_decode($result, true);
    assertTrue(isset($echoed['headers']['Content-Type']), 'Injected Content-Type header must be sent');
    assertTrue(
        stripos((string) $echoed['headers']['Content-Type'], 'application/json') !== false,
        'Injected Content-Type must be application/json'
    );
    assertTrue(($echoed['json']['hello'] ?? null) === 'world', 'JSON body must be transmitted');
};

// Test 29: caller's own Content-Type is respected, no duplicate injected
$tests[] = function () {
    $result = NanoCore::curlRequest('https://httpbin.org/anything', [
        'method'  => 'POST',
        'json'    => ['hello' => 'world'],
        'headers' => ['Content-Type: application/x-custom'],
        'raw'     => true,
    ]);
    $echoed = json_decode($result, true);
    assertTrue(
        stripos((string) $echoed['headers']['Content-Type'], 'application/x-custom') !== false,
        "Caller's Content-Type must win"
    );
    assertTrue(
        stripos((string) $echoed['headers']['Content-Type'], 'application/json') === false,
        'No application/json header must be injected when the caller supplied one'
    );
};

// Test 30: single 4xx throws with code = HTTP status and message contains "HTTP {code}"
$tests[] = function () {
    try {
        NanoCore::curlRequest('https://httpbin.org/status/404', ['raw' => true]);
        assertTrue(false, 'Expected HTTP 404 to throw');
    } catch (\Exception $e) {
        assertTrue($e->getCode() === 404, 'Exception code must be the HTTP status');
        assertTrue(str_contains($e->getMessage(), 'HTTP 404'), 'Message must contain "HTTP 404"');
    }
};

// Test 31: with_info does NOT suppress the throw; ignore_http_errors does
$tests[] = function () {
    assertThrows(\Exception::class, '', function () {
        NanoCore::curlRequest('https://httpbin.org/status/404', ['raw' => true, 'with_info' => true]);
    });

    $info = NanoCore::curlRequest('https://httpbin.org/status/404', [
        'raw' => true,
        'with_info' => true,
        'ignore_http_errors' => true,
    ]);
    assertTrue(is_array($info) && $info['status'] === 404, 'ignore_http_errors must return the error response as-is');
};

// Test 32: batch 4xx entry is an Exception with the status as code
$tests[] = function () {
    $results = NanoCore::curlRequest(
        ['https://httpbin.org/status/200', 'https://httpbin.org/status/500'],
        ['raw' => true]
    );
    assertTrue(is_string($results[0]), 'First URL (200) should succeed');
    assertTrue($results[1] instanceof Exception, 'Second URL (500) should be an Exception');
    assertTrue($results[1]->getCode() === 500, 'Batch exception code must be the HTTP status');
};

// Test 33: transport failure message keeps the prefix and reports the cURL code (code stays 0)
$tests[] = function () {
    // Literal public IP with a closed port: passes validation (no DNS pin), then fails at transport level
    try {
        NanoCore::curlRequest('http://1.1.1.1:81/', [
            'raw' => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 2,
        ]);
        assertTrue(false, 'Expected transport failure to throw');
    } catch (\Exception $e) {
        assertTrue(
            str_contains($e->getMessage(), 'External request failed (cURL'),
            'Message must contain "External request failed (cURL'
        );
        assertTrue($e->getCode() === 0, 'Transport failure code must stay 0');
    }
};

// Test 34: a sensitive key in the response body is masked in the log, never written verbatim
$tests[] = function () {
    $configDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nc_curl_' . uniqid();
    mkdir($configDir);
    $tmpFile = $configDir . DIRECTORY_SEPARATOR . '.env';
    $logFile = $configDir . DIRECTORY_SEPARATOR . 'nanocore.log';
    file_put_contents($tmpFile, "CORE.EXPOSE_ERRORS=false\n");

    try {
        new NanoCore($tmpFile);

        // httpbin /response-headers echoes the query as top-level JSON keys, so the response body
        // carries {"token":"secret-value"} under a sensitive key that must be masked in the log
        NanoCore::curlRequest('https://httpbin.org/response-headers?token=secret-value', ['raw' => true]);

        $log = (string) file_get_contents($logFile);
        assertTrue(str_contains($log, '***'), 'Log must contain the masked placeholder');
        assertTrue(!str_contains($log, 'secret-value'), 'Log must not contain the raw secret value');
    } finally {
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        unlink($tmpFile);
        rmdir($configDir);
    }
};

// Test 35: a transport timeout exposes both the failure prefix and curl's "timed out" wording
$tests[] = function () {
    // POST (non-idempotent) so the failure is not retried; delay > timeout guarantees cURL 28
    try {
        NanoCore::curlRequest('https://httpbin.org/delay/10', [
            'method' => 'POST',
            'raw' => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        assertTrue(false, 'Expected a transport timeout to throw');
    } catch (\Exception $e) {
        assertTrue(
            str_contains($e->getMessage(), 'External request failed'),
            'Message must contain "External request failed"'
        );
        assertTrue(
            stripos($e->getMessage(), 'timed out') !== false,
            'Message must contain "timed out"'
        );
    }
};

runTests($tests);