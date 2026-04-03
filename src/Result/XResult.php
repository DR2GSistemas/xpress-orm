<?php

declare(strict_types=1);

namespace Xpress\Orm\Result;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use JsonSerializable;

final class XResult implements JsonSerializable, Countable, IteratorAggregate
{
    private mixed $value;
    private ?XError $error;
    private bool $success;
    private int $httpCode;

    private function __construct(mixed $value, ?XError $error, bool $success, int $httpCode = 200)
    {
        $this->value = $value;
        $this->error = $error;
        $this->success = $success;
        $this->httpCode = $httpCode;
    }

    public static function ok(mixed $value = null, int $httpCode = 200): self
    {
        return new self($value, null, true, $httpCode);
    }

    public static function fail(string $message, int $code = 500, array $data = []): self
    {
        return new self(null, new XError($message, $code, $data), false, $code);
    }

    public static function error(XError $error): self
    {
        return new self(null, $error, false, $error->getCode());
    }

    public static function fromThrowable(\Throwable $e, bool $hideInternal = true): self
    {
        $message = $hideInternal ? 'An error occurred' : $e->getMessage();
        $code = $e->getCode() ?: 500;

        $data = ['type' => 'database_error'];
        
        if (!$hideInternal) {
            $data['exception'] = get_class($e);
            $data['file'] = $e->getFile();
            $data['line'] = $e->getLine();
            $data['trace'] = $e->getTraceAsString();
        }

        return self::fail($message, $code, $data);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getValueOr(mixed $default): mixed
    {
        return $this->success ? $this->value : $default;
    }

    public function getError(): ?XError
    {
        return $this->error;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error?->getMessage();
    }

    public function getErrorCode(): int
    {
        return $this->error?->getCode() ?? 0;
    }

    public function getErrorData(): array
    {
        return $this->error?->getData() ?? [];
    }

    public function getCode(): int
    {
        return $this->httpCode;
    }

    public function withCode(int $code): self
    {
        if ($this->isSuccess()) {
            return self::ok($this->value, $code);
        }
        return new self(null, $this->error?->withCode($code), false, $code);
    }

    public function isNotFound(): bool
    {
        return $this->isFailure() && $this->getErrorCode() === 404;
    }

    public function isConflict(): bool
    {
        return $this->isFailure() && $this->getErrorCode() === 409;
    }

    public function isServerError(): bool
    {
        return $this->isFailure() && $this->getErrorCode() >= 500;
    }

    public function unwrap(): mixed
    {
        if ($this->isFailure()) {
            throw new \RuntimeException(
                'Called unwrap on a failed Result: ' . $this->error?->getMessage()
            );
        }
        return $this->value;
    }

    public function unwrapOr(mixed $default): mixed
    {
        return $this->success ? $this->value : $default;
    }

    public function unwrapOrNull(): mixed
    {
        return $this->success ? $this->value : null;
    }

    public function map(callable $fn): self
    {
        if ($this->isFailure()) {
            return $this;
        }
        return self::ok($fn($this->value), $this->httpCode);
    }

    public function mapError(callable $fn): self
    {
        if ($this->isSuccess()) {
            return $this;
        }
        return self::error($fn($this->error));
    }

    public function andThen(callable $fn): self
    {
        if ($this->isFailure()) {
            return $this;
        }
        $result = $fn($this->value);
        if (!$result instanceof self) {
            return self::ok($result);
        }
        return $result;
    }

    public function orElse(callable $fn): self
    {
        if ($this->isSuccess()) {
            return $this;
        }
        $result = $fn($this->error);
        if (!$result instanceof self) {
            return self::ok($result);
        }
        return $result;
    }

    public function toArray(): array
    {
        if ($this->isSuccess()) {
            return [
                'success' => true,
                'data' => $this->value,
                'error' => null,
            ];
        }
        return [
            'success' => false,
            'data' => null,
            'error' => [
                'message' => $this->error?->getMessage(),
                'code' => $this->error?->getCode(),
                'data' => $this->error?->getData(),
            ],
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function count(): int
    {
        return $this->isSuccess() ? 1 : 0;
    }

    public function getIterator(): ArrayIterator
    {
        if ($this->isSuccess() && is_array($this->value)) {
            return new ArrayIterator($this->value);
        }
        return new ArrayIterator($this->isSuccess() ? [$this->value] : []);
    }

    public function __toString(): string
    {
        if ($this->isSuccess()) {
            return 'XResult::ok(' . json_encode($this->value) . ')';
        }
        return 'XResult::fail("' . $this->getMessage() . '", ' . $this->getErrorCode() . ')';
    }

    public function getMessage(): string
    {
        return $this->success ? 'OK' : ($this->error?->getMessage() ?? 'Unknown error');
    }
}
