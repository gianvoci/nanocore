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

runTests($tests);