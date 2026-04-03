# Xpress ORM - ORM Ligero para PHP 8.4

Xpress ORM es un mapper objeto-relacional ligero y rápido para PHP 8.4, diseñado con una sintaxis limpia usando atributos. Soporta MariaDB/MySQL con auto-migrate, relaciones completas y query builder integrado.

## Características

- **Atributos PHP 8.4** - Definiciones de entidades declarativas y limpìas
- **Relaciones completas** - One-to-One, One-to-Many, Many-to-One, Many-to-Many
- **Auto-migrate** - Sincronización automática de schema de base de datos
- **Query Builder** - Constructor de queries encadenable con escape automático (OWASP)
- **Soft Deletes** - Soporte nativo para eliminación suave
- **Timestamps automáticos** - created_at y updated_at automáticos
- **Repositorios** - Patrón Repository con métodos personalizados
- **Transacciones** - Soporte completo para transacciones
- **Hydrator** - Conversión bidireccional entre entidades y arrays
- **Seguridad** - Prepared statements obligatorios contra SQL Injection

## Requisitos

- PHP 8.4 o superior
- Extensión PDO instalada
- MariaDB 10.5+ o MySQL 8.0+ (compatible con PostgreSQL con driver pdo_pgsql)

## Instalación

```bash
composer require azul/xpress-orm
```

## Configuración Rápida

### 1. Conexión a la Base de Datos

```php
use Xpress\Orm\Connection\XConnection;

$connection = new XConnection([
    'driver' => 'pdo_mysql',
    'host' => 'localhost',
    'database' => 'mi_app',
    'username' => 'root',
    'password' => 'secret',
    'charset' => 'utf8mb4'
]);
```

### 2. Entity Manager

```php
use Xpress\Orm\Entity\XEntityManager;

$em = new XEntityManager($connection);
```

### 3. Auto-migrate (crear/actualizar tablas)

```php
use Xpress\Orm\Schema\XAutoMigrate;

$migrate = new XAutoMigrate($connection);
$migrate->updateSchema([User::class, Post::class, Category::class]);
```

## Definición de Entidades

### Entidad Básica con Atributos

```php
<?php

namespace App\Models;

use Xpress\Orm\Attributes\Entity\{XEntity, XColumn, XId, XIndex};
use Xpress\Orm\Traits\TimestampableTrait;
use Xpress\Orm\Traits\SoftDeleteTrait;

#[XEntity(table: 'users')]
#[XIndex(columns: ['email'], unique: true)]
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

    #[XColumn(type: 'enum', enum: ['admin', 'user', 'guest'], default: 'user')]
    private string $role = 'user';

    // Getters y setters...
}
```

### Tipos de Columnas Soportados

| Tipo | Descripción |
|------|-------------|
| `int` | Entero |
| `bigint` | Entero grande |
| `varchar(length)` | Cadena de texto |
| `text` | Texto largo |
| `boolean` | Booleano (TINYINT) |
| `decimal(precision, scale)` | Número decimal |
| `float` | Flotante |
| `date` | Fecha |
| `datetime` | Fecha y hora |
| `timestamp` | Timestamp |
| `enum(['a', 'b', 'c'])` | Enum |
| `json` | JSON |
| `blob` | Datos binarios |

### Opciones de Columnas

```php
#[XColumn(
    type: 'varchar',
    length: 255,
    name: 'custom_column_name',    // Nombre de columna (opcional)
    nullable: true,                 // Permitir nulos
    default: 'value',              // Valor por defecto
    unique: true,                 // Índice único
    increment: true,               // Auto-incremento
    comment: 'Descripción'         // Comentario
)]
```

## Relaciones entre Entidades

### One-to-Many (Un usuario tiene muchos posts)

```php
#[XEntity(table: 'users')]
class User
{
    #[XRelation(oneToMany: Post::class, mappedBy: 'author')]
    private ?Collection $posts = null;

    public function getPosts(): Collection
    {
        return $this->posts ?? new Collection();
    }
}

#[XEntity(table: 'posts')]
class Post
{
    #[XRelation(manyToOne: User::class, joinColumn: 'user_id')]
    private ?User $author = null;

    public function getAuthor(): ?User
    {
        return $this->author;
    }
}
```

### Many-to-Many (Posts tienen muchos Tags)

```php
class Post
{
    #[XRelation(manyToMany: Tag::class, joinTable: 'post_tags')]
    private ?Collection $tags = null;

    public function addTag(Tag $tag): void
    {
        $this->tags[] = $tag;
    }
}
```

## Traits Disponibles

### TimestampableTrait

Añade `created_at` y `updated_at` automáticos:

```php
use Xpress\Orm\Traits\TimestampableTrait;

class User
{
    use TimestampableTrait;
    
    // Los timestamps se actualizan automáticamente al guardar
}
```

### SoftDeleteTrait

Añade eliminación suave con `deleted_at`:

```php
use Xpress\Orm\Traits\SoftDeleteTrait;

class User
{
    use SoftDeleteTrait;
    
    // $user->softDelete() marca deleted_at
    // $user->restore() limpia deleted_at
    // $em->delete($user) usa soft delete por defecto
}
```

## CRUD con Entity Manager

### Crear

```php
$user = new User();
$user->setName('Juan Pérez');
$user->setEmail('juan@ejemplo.com');
$user->hashPassword('secret123');

$em->save($user);
echo "ID: " . $user->getId(); // ID: 1
```

### Leer

```php
// Por ID
$user = $em->find(User::class, 1);

// Por criterios
$user = $em->findOneBy(User::class, ['email' => 'juan@ejemplo.com']);

// Múltiples resultados
$admins = $em->findBy(User::class, ['role' => 'admin']);

// Todos los registros
$users = $em->findAll(User::class);

// Contar
$total = $em->count(User::class, ['status' => 'active']);

// Verificar existencia
$exists = $em->exists(User::class, 1);
```

### Actualizar

```php
$user = $em->find(User::class, 1);
$user->setName('Juan Actualizado');
$em->save($user); // Actualiza automáticamente
```

### Eliminar

```php
// Soft delete (por defecto)
$em->delete($user);

// Hard delete (eliminación permanente)
$em->delete($user, hard: true);

// Eliminar por ID
$em->deleteById(User::class, 1);

// Eliminar todos
$em->deleteAll(User::class, ['role' => 'inactive']);
```

## Repositorios

### Definir Repositorio

```php
<?php

namespace App\Repositories;

use App\Models\User;
use Xpress\Orm\Attributes\Repository\XRepository;
use Xpress\Orm\Repository\XBaseRepository;

#[XRepository(entity: User::class)]
class UserRepository extends XBaseRepository
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOneByColumn('email', $email);
    }

    public function findActiveUsers(): array
    {
        return $this->findBy(['status' => 'active']);
    }

    public function search(string $query): array
    {
        return $this->search($query, ['name', 'email']);
    }

    public function paginated(int $page = 1, int $perPage = 20): array
    {
        return $this->paginate($page, $perPage);
    }
}
```

### Usar Repositorio

```php
$repo = $em->getRepository(UserRepository::class);

// Métodos heredados
$user = $repo->find(1);
$admins = $repo->findBy(['role' => 'admin']);
$repo->save($user);

// Métodos personalizados
$user = $repo->findByEmail('juan@ejemplo.com');
$active = $repo->findActiveUsers();
$search = $repo->search('Juan');

// Paginación
$pagination = $repo->paginated(2, 10);
// ['items' => [...], 'total' => 50, 'page' => 2, 'per_page' => 10, 'pages' => 5]
```

## Query Builder

### Uso Básico

```php
$users = $em->createQuery(User::class)
    ->select('*')
    ->from('users')
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->getResult();
```

### Condiciones

```php
// WHERE simple
->where('age', 25)
->where('status', 'active')

// WHERE IN
->whereIn('id', [1, 2, 3])

// WHERE NULL
->whereNull('deleted_at')
->whereNotNull('published_at')

// WHERE BETWEEN
->whereBetween('age', 18, 65)

// WHERE LIKE
->whereLike('name', 'Juan')           // %Juan%
->whereLike('name', 'Juan', 'start')  // Juan%
->whereLike('name', 'Juan', 'end')    // %Juan

// OR
->where('status', 'active')
->orWhere('role', 'admin')

// RAW
->whereRaw('YEAR(created_at) = ?', [2024])
```

### Joins

```php
->select(['u.*', 'COUNT(p.id) as post_count'])
->from('users', 'u')
->leftJoin('posts', 'u.id = p.user_id', 'p')
->groupBy('u.id')
->having('COUNT(p.id) > ?', 5)
```

### Paginación

```php
// Página 2, 10 items por página
->page(2, 10)
// LIMIT 10 OFFSET 10
```

### Obtener Resultados

```php
->getResult()      // Array de resultados
->getOne()         // Un resultado o null
->getColumn()      // Primera columna
->getValue()       // Alias de getColumn
->count()          // Conteo
->exists()         // Boolean
```

## Transacciones

```php
try {
    $em->beginTransaction();

    $user1 = new User();
    $user1->setName('Usuario 1');
    $user1->setEmail('u1@ejemplo.com');
    $em->save($user1);

    $user2 = new User();
    $user2->setName('Usuario 2');
    $user2->setEmail('u2@ejemplo.com');
    $em->save($user2);

    $em->commit();
} catch (\Exception $e) {
    $em->rollback();
}
```

## Auto-migrate

### Sincronizar Schema

```php
$migrate = new XAutoMigrate($connection);

// Verbose mode
$migrate->setVerbose(true);

// Actualizar todas las tablas
$logs = $migrate->updateSchema([User::class, Post::class, Category::class]);

// Crear schema (solo crea, no modifica)
// $migrate->createSchema([...]);

// Eliminar schema
// $migrate->dropSchema([...]);
```

### Lo que hace Auto-migrate

1. Crea tablas que no existen
2. Añade columnas nuevas
3. Crea índices definidos
4. Crea tablas de join para Many-to-Many
5. Añade foreign keys

## Ejemplo Completo

```php
<?php

require_once 'vendor/autoload.php';

use App\Models\{User, Post, Category};
use App\Repositories\{UserRepository, PostRepository};
use Xpress\Orm\Connection\XConnection;
use Xpress\Orm\Entity\XEntityManager;
use Xpress\Orm\Schema\XAutoMigrate;

$connection = new XConnection([
    'driver' => 'pdo_mysql',
    'host' => 'localhost',
    'database' => 'mi_blog',
    'username' => 'root',
    'password' => ''
]);

// Sincronizar schema
$migrate = new XAutoMigrate($connection);
$migrate->updateSchema([User::class, Post::class, Category::class]);

// Entity Manager
$em = new XEntityManager($connection);

// Crear usuario
$user = new User();
$user->setName('Juan Pérez');
$user->setEmail('juan@ejemplo.com');
$user->setRole('admin');
$user->hashPassword(password_hash('secret123', PASSWORD_DEFAULT));
$em->save($user);

// Crear categoría
$category = new Category();
$category->setName('Tecnología');
$category->setDescription('Artículos de tecnología');
$em->save($category);

// Crear post
$post = new Post();
$post->setTitle('Mi Primer Post');
$post->setContent('<p>Contenido del post...</p>');
$post->setAuthor($user);
$post->setCategory($category);
$post->publish();
$em->save($post);

// Consultar con repositorio
$repo = $em->getRepository(PostRepository::class);
$published = $repo->findPublished();

// Paginación
$pagination = $repo->paginate(1, 10);

// Búsqueda
$results = $repo->searchPosts('primer');

// Relaciones con eager loading
$post = $repo->findWithRelations(1);
echo $post->getAuthor()->getName();
echo $post->getCategory()->getName();
```

## Seguridad OWASP

### Prepared Statements

Todos los queries usan prepared statements automáticamente:

```php
// ✅ Seguro
$qb->where('email', $email); // Prepared statement

// ❌ Peligroso (no recomendado)
// Usar whereRaw solo con parámetros
$qb->whereRaw('email = ?', [$email]);
```

### Prevención de SQL Injection

- NUNCA concatenar variables directamente en SQL
- Usar siempre `->setParameter()` o el sistema de condiciones
- Sanitizar inputs del usuario antes de usarlos

## Mejores Prácticas

### 1. Siempre usar tipos estrictos

```php
declare(strict_types=1);
```

### 2. No exponer passwords en arrays

```php
public function toArray(): array
{
    $data = parent::toArray();
    unset($data['password']); // Nunca incluir passwords
    return $data;
}
```

### 3. Usar transacciones para operaciones múltiples

```php
$em->beginTransaction();
try {
    // Operaciones...
    $em->commit();
} catch (\Exception $e) {
    $em->rollback();
}
```

### 4. Preferir repositorios sobre queries directas

```php
// ✅ Mejor
$repo = $em->getRepository(UserRepository::class);
$user = $repo->findByEmail($email);

// ✅ Aceptable para queries complejas
$qb = $em->createQuery(User::class);
```

## API Reference

### XEntityManager

```php
$em->find($class, $id);                  // Buscar por ID
$em->findOneBy($class, $criteria);       // Uno por criterios
$em->findBy($class, $criteria, $options, $limit, $offset);
$em->findAll($class);                     // Todos
$em->save($entity);                      // Crear/actualizar
$em->delete($entity, $hard = false);    // Eliminar
$em->count($class, $criteria);          // Contar
$em->exists($class, $id);               // Verificar existencia
$em->createQuery($class);               // Query builder
$em->getRepository($repoClass);         // Repositorio
$em->extract($entity);                   // Entidad a array
$em->beginTransaction();
$em->commit();
$em->rollback();
```

### XQueryBuilder

```php
$qb->select($columns);
$qb->from($table, $alias);
$qb->where($column, $value);
$qb->whereIn($column, $values);
$qb->whereNull($column);
$qb->whereNotNull($column);
$qb->whereBetween($column, $val1, $val2);
$qb->whereLike($column, $value);
$qb->whereRaw($sql, $params);
$qb->orWhere($column, $value);
$qb->join($table, $condition, $alias, $type);
$qb->leftJoin($table, $condition, $alias);
$qb->innerJoin($table, $condition, $alias);
$qb->groupBy($columns);
$qb->having($condition, $value);
$qb->orderBy($column, $direction);
$qb->limit($limit);
$qb->offset($offset);
$qb->page($page, $perPage);
$qb->setParameter($key, $value);
$qb->getParameters();
$qb->getSQL();
$qb->getResult();
$qb->getOne();
$qb->count();
$qb->exists();
$qb->reset();
```

### XBaseRepository

```php
$repo->find($id);
$repo->findOne($criteria);
$repo->findBy($criteria, $options, $limit, $offset);
$repo->findAll();
$repo->save($entity);
$repo->saveAll($entities);
$repo->delete($entity, $hard = false);
$repo->count($criteria);
$repo->exists($id);
$repo->refresh($entity);
$repo->detach($entity);
$repo->createQueryBuilder();
$repo->findWith($relations);
$repo->paginate($page, $perPage, $criteria);
$repo->first($criteria);
$repo->last($criteria);
$repo->search($query, $fields);
$repo->existsBy($criteria);
```

## Licencia

MIT License - ver archivo [LICENSE](LICENSE) para más detalles.

## Contributing

1. Fork el repositorio
2. Crea una rama para tu feature (`git checkout -b feature/nueva-funcion`)
3. Commit tus cambios (`git commit -am 'Agregar nueva función'`)
4. Push a la rama (`git push origin feature/nueva-funcion`)
5. Crea un Pull Request
