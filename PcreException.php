<?php declare(strict_types=1);

namespace kafene;

class PcreException extends \RuntimeException
{
    protected const CODE_MESSAGE_MAP = [
        \PREG_NO_ERROR              => 'No error',
        \PREG_INTERNAL_ERROR        => 'Internal error',
        \PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit exceeded',
        \PREG_RECURSION_LIMIT_ERROR => 'Recursion limit exceeded',
        \PREG_BAD_UTF8_ERROR        => 'Malformed UTF-8 data',
        \PREG_BAD_UTF8_OFFSET_ERROR => 'UTF-8 offset did not correspond to the beginning of a valid UTF-8 code point',
        \PREG_JIT_STACKLIMIT_ERROR  => 'JIT stack limit exceeded',
    ];

    /** {@inheritdoc} */
    public function __construct(string $message = null, int $code = null, \Throwable $previous = null)
    {
        $code = $code ?? 0;
        $message = $message ?? static::getMessageForCode($code);

        parent::__construct($message, $code, $previous);
    }

    /** @param int|null $code PCRE error code; detected automatically if not provided. */
    public static function create(int $code = null): self
    {
        return new static(null, $code ?? \preg_last_error());
    }

    /** @param int $code PCRE error code. */
    public static function getMessageForCode(int $code): string
    {
        return static::CODE_MESSAGE_MAP[$code] ?? 'Unknown error';
    }
}
