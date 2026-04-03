<?php

declare(strict_types=1);

namespace Xpress\Orm\Result;

final class XError implements \JsonSerializable
{
    public function __construct(
        private readonly string $message,
        private readonly int $code = 500,
        private readonly array $data = []
    ) {}

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'code' => $this->code,
            'data' => $this->data,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return "XError({$this->code}, \"{$this->message}\")";
    }

    public function withCode(int $code): self
    {
        return new self($this->message, $code, $this->data);
    }

    public function withMessage(string $message): self
    {
        return new self($message, $this->code, $this->data);
    }

    public function withData(array $data): self
    {
        return new self($this->message, $this->code, array_merge($this->data, $data));
    }

    public static function badRequest(string $message = 'Bad Request', array $data = []): self
    {
        return new self($message, 400, $data);
    }

    public static function unauthorized(string $message = 'Unauthorized', array $data = []): self
    {
        return new self($message, 401, $data);
    }

    public static function forbidden(string $message = 'Forbidden', array $data = []): self
    {
        return new self($message, 403, $data);
    }

    public static function notFound(string $message = 'Not Found', array $data = []): self
    {
        return new self($message, 404, $data);
    }

    public static function conflict(string $message = 'Conflict', array $data = []): self
    {
        return new self($message, 409, $data);
    }

    public static function unprocessable(string $message = 'Unprocessable Entity', array $data = []): self
    {
        return new self($message, 422, $data);
    }

    public static function validation(array $errors): self
    {
        return new self('Validation failed', 422, ['errors' => $errors]);
    }

    public static function internal(string $message = 'Internal Server Error', array $data = []): self
    {
        return new self($message, 500, $data);
    }

    public static function database(string $message, array $data = []): self
    {
        return new self($message, 500, array_merge($data, ['type' => 'database_error']));
    }
}
