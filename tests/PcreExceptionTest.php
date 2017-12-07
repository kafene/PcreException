<?php

namespace kafene;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Error\Warning;
use function preg_replace,
             preg_match,
             preg_match_all,
             preg_last_error,
             urldecode,
             ini_get,
             ini_set,
             ini_restore,
             str_repeat,
             error_get_last;
use const PREG_NO_ERROR,
          PREG_INTERNAL_ERROR,
          PREG_BACKTRACK_LIMIT_ERROR,
          PREG_RECURSION_LIMIT_ERROR,
          PREG_BAD_UTF8_ERROR,
          PREG_BAD_UTF8_OFFSET_ERROR,
          PREG_JIT_STACKLIMIT_ERROR;

/**
 * PcreException Test Case
 *
 * These tests are adapted from PHP's internal PCRE tests.
 * Copyright (c) 1999 - 2017 The PHP Group. All rights reserved.
 * Licensed under the PHP License, version 3.01.
 * @see {@link https://github.com/php/php-src/tree/master/ext/pcre/tests}
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState enabled
 */
class PcreExceptionTest extends TestCase
{
    public function testNoError()
    {
        $result = preg_replace('/a/', 'b', 'abc');
        $this->assertSame('bbc', $result);
        $this->assertSame(PREG_NO_ERROR, PcreException::create()->getCode());
    }

    public function testInternalErrorException()
    {
        $sRegex = "/([A-Z]|[a-z]|[0-9]| |Ñ|ñ|!|&quot;|%|&amp;|'|´|-|:|;|>|=|&lt;|@|_|,|\{|\}|`|~|á|é|í|ó|ú|Á|É|Í|Ó|Ú|ü|Ü){1,300}/";
        $sTest = "Hello world";

        $this->expectException(Warning::class);
        $this->expectExceptionMessageRegExp('/regular expression is too large at offset \d+/');
        preg_match($sRegex, $sTest);
    }

    public function testInternalErrorWarning()
    {
        $sRegex = "/([A-Z]|[a-z]|[0-9]| |Ñ|ñ|!|&quot;|%|&amp;|'|´|-|:|;|>|=|&lt;|@|_|,|\{|\}|`|~|á|é|í|ó|ú|Á|É|Í|Ó|Ú|ü|Ü){1,300}/";
        $sTest = "Hello world";

        $expectedMessage = 'preg_match(): Compilation failed: regular expression is too large at offset %s';

        $result = @preg_match($sRegex, $sTest);
        $lastError = error_get_last();
        $this->assertInternalType('array', $lastError);
        $this->assertArrayHasKey('message', $lastError);
        $this->assertStringMatchesFormat($expectedMessage, $lastError['message']);
        $this->assertSame(false, $result);

        $this->markTestSkipped('See PHP Bug #74183 - preg_last_error not returning error code after error (https://bugs.php.net/bug.php?id=74183).');
        return;

        $this->assertSame(PREG_INTERNAL_ERROR, PcreException::create()->getCode());
    }

    /** Backtracking limit */
    public function testBacktrackLimitError()
    {
        if (@preg_match_all('/\p{N}/', '0123456789', $dummy) === false) {
            $this->markTestSkipped("skip no support for \p support PCRE library");
            return;
        }

        ini_set('pcre.backtrack_limit', '2');
        ini_set('pcre.jit', '0');

        $result = preg_match_all('/.*\p{N}/', '0123456789', $dummy);
        $this->assertSame(false, $result);
        $this->assertSame(PREG_BACKTRACK_LIMIT_ERROR, PcreException::create()->getCode());

        $result = preg_match_all('/\p{Nd}/', '0123456789', $dummy);
        $this->assertSame(10, $result);
        $this->assertSame(PREG_NO_ERROR, PcreException::create()->getCode());

        ini_restore('pcre.backtrack_limit');
        ini_restore('pcre.jit');
    }

    /** PCRE Recursion limit */
    public function testRecursionLimitError()
    {
        if (@preg_match_all('/\p{N}/', '0123456789', $dummy) === false) {
            $this->markTestSkipped("skip no support for \p support PCRE library");
            return;
        }

        ini_set('pcre.recursion_limit', '2');
        ini_set('pcre.jit', '0');

        $result = preg_match_all('/\p{Ll}(\p{L}((\p{Ll}\p{Ll})))/', 'aeiou', $dummy);
        $this->assertSame(false, $result);
        $this->assertSame(PREG_RECURSION_LIMIT_ERROR, PcreException::create()->getCode());

        $result = preg_match_all('/\p{Ll}\p{L}\p{Ll}\p{Ll}/', 'aeiou', $dummy);
        $this->assertSame(1, $result);
        $this->assertSame(PREG_NO_ERROR, PcreException::create()->getCode());

        ini_restore('pcre.recursion_limit');
        ini_restore('pcre.jit');
    }

    /** preg_replace() and invalid UTF8 */
    public function testBadUtf8Error()
    {
        if (@preg_match('/./u', '') === false) {
            $this->markTestSkipped('skip no utf8 support in PCRE library');
            return;
        }

        $string = urldecode("search%e4");
        $result = preg_replace("#(&\#x*)([0-9A-F]+);*#iu","$1$2;",$string);
        $this->assertSame(null, $result);
        $this->assertSame(PREG_BAD_UTF8_ERROR, PcreException::create()->getCode());
    }

    /** preg_replace() and invalid UTF8 offset */
    public function testBadUtf8OffsetError()
    {
        if (@preg_match('/./u', '') === false) {
            $this->markTestSkipped('skip no utf8 support in PCRE library');
            return;
        }

        $string = "\xc3\xa9 uma string utf8 bem formada";
        $result = preg_match('~.*~u', $string, $m, 0, 1);
        $this->assertSame(false, $result);
        $this->assertSame([], $m);
        $this->assertSame(PREG_BAD_UTF8_OFFSET_ERROR, PcreException::create()->getCode());

        $result = preg_match('~.*~u', $string, $m, 0, 2);
        $this->assertSame(1, $result);
        $this->assertSame([" uma string utf8 bem formada"], $m);
        $this->assertSame(PREG_NO_ERROR, PcreException::create()->getCode());
    }

    /** Test preg_match() function : error conditions - jit stacklimit exhausted */
    public function testJitStackLimiterror()
    {
        if (ini_get("pcre.jit") === false) {
            $this->markTestSkipped("skip no jit built");
        }

        $result = preg_match('/^(foo)+$/', str_repeat('foo', 1024*8192));
        $this->assertSame(false, $result);
        $this->assertSame(PREG_JIT_STACKLIMIT_ERROR, PcreException::create()->getCode());
    }

    public function testRepeatedUsageMaintainsCorrectError()
    {
        $string = urldecode("search%e4");
        $result = preg_replace("#(&\#x*)([0-9A-F]+);*#iu","$1$2;",$string);
        $this->assertSame(null, $result);
        $this->assertSame(PREG_BAD_UTF8_ERROR, PcreException::create()->getCode());
        $this->assertSame(PREG_BAD_UTF8_ERROR, PcreException::create()->getCode());

        $result = preg_replace('/a/', 'b', 'abc');
        $this->assertSame('bbc', $result);
        $this->assertSame(PREG_NO_ERROR, preg_last_error());
        $this->assertSame(PREG_NO_ERROR, PcreException::create()->getCode());
        $this->assertSame(PREG_NO_ERROR, PcreException::create()->getCode());
    }

    public function testArbitraryCodesWork()
    {
        $exception = PcreException::create(12345);
        $this->expectException(PcreException::class);
        $this->expectExceptionCode(12345);
        $this->expectExceptionMessage('Unknown error');
        throw $exception;
    }
}
