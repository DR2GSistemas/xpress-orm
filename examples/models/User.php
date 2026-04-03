<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use Xpress\Orm\Attributes\Entity\{XEntity, XColumn, XId, XRelation, XIndex};
use Xpress\Orm\Traits\TimestampableTrait;
use Xpress\Orm\Traits\SoftDeleteTrait;

#[XEntity(table: 'users')]
#[XIndex(columns: ['email'], unique: true)]
#[XIndex(columns: ['role', 'status'])]
class User
{
    use TimestampableTrait;
    use SoftDeleteTrait;

    #[XId]
    #[XColumn(type: 'int', increment: true)]
    private ?int $id = null;

    #[XColumn(type: 'varchar', length: 100)]
    private string $name;

    #[XColumn(type: 'varchar', length: 255, unique: true)]
    private string $email;

    #[XColumn(type: 'varchar', length: 255)]
    private ?string $password = null;

    #[XColumn(type: 'enum', enum: ['admin', 'editor', 'user', 'guest'], default: 'user')]
    private string $role = 'user';

    #[XColumn(type: 'enum', enum: ['active', 'inactive', 'banned'], default: 'active')]
    private string $status = 'active';

    #[XColumn(type: 'varchar', length: 255, nullable: true)]
    private ?string $avatar = null;

    #[XColumn(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[XRelation(oneToMany: Post::class, mappedBy: 'author')]
    private ?Collection $posts = null;

    #[XRelation(oneToMany: Comment::class, mappedBy: 'user')]
    private ?Collection $comments = null;

    #[XRelation(manyToMany: Role::class, joinTable: 'user_roles')]
    private ?Collection $roles = null;

    public function __construct()
    {
        $this->posts = new Collection();
        $this->comments = new Collection();
        $this->roles = new Collection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function hashPassword(string $plain): void
    {
        $this->password = password_hash($plain, PASSWORD_DEFAULT);
    }

    public function verifyPassword(string $plain): bool
    {
        return $this->password !== null && password_verify($plain, $this->password);
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): void
    {
        $this->avatar = $avatar;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }

    public function getPosts(): Collection
    {
        return $this->posts ?? new Collection();
    }

    public function setPosts(Collection $posts): void
    {
        $this->posts = $posts;
    }

    public function getComments(): Collection
    {
        return $this->comments ?? new Collection();
    }

    public function setComments(Collection $comments): void
    {
        $this->comments = $comments;
    }

    public function getRoles(): Collection
    {
        return $this->roles ?? new Collection();
    }

    public function setRoles(Collection $roles): void
    {
        $this->roles = $roles;
    }

    public function addRole(Role $role): void
    {
        $this->roles[] = $role;
    }

    public function removeRole(Role $role): void
    {
        $key = array_search($role, $this->roles->toArray(), true);
        if ($key !== false) {
            unset($this->roles[$key]);
        }
    }

    public function hasRole(string $roleName): bool
    {
        foreach ($this->roles as $role) {
            if ($role->getName() === $roleName) {
                return true;
            }
        }
        return false;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'avatar' => $this->avatar,
            'bio' => $this->bio,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}

class Collection implements \ArrayAccess, \Iterator, \Countable
{
    private array $items = [];
    private int $position = 0;

    public function __construct(array $items = [])
    {
        $this->items = $items;
        $this->position = 0;
    }

    public function add(mixed $item): void
    {
        $this->items[] = $item;
    }

    public function remove(mixed $item): void
    {
        $key = array_search($item, $this->items, true);
        if ($key !== false) {
            unset($this->items[$key]);
            $this->items = array_values($this->items);
        }
    }

    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function last(): mixed
    {
        return $this->items[count($this->items) - 1] ?? null;
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    public function filter(callable $callback): array
    {
        return array_filter($this->items, $callback);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    public function current(): mixed
    {
        return $this->items[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }
}
