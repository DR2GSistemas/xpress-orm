<?php

declare(strict_types=1);

namespace Xpress\Orm\Tests;

use PHPUnit\Framework\TestCase;
use Xpress\Orm\Connection\XConnection;
use Xpress\Orm\Connection\XQueryBuilder;
use Xpress\Orm\Attributes\Entity\{XEntity, XColumn, XId, XIndex};

#[XEntity(table: 'test_users')]
class TestUser
{
    #[XId]
    #[XColumn(type: 'int', increment: true)]
    private ?int $id = null;

    #[XColumn(type: 'varchar', length: 100)]
    private string $name;

    #[XColumn(type: 'varchar', length: 255, unique: true)]
    private string $email;

    #[XColumn(type: 'int', default: 0)]
    private int $age = 0;

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

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): void
    {
        $this->age = $age;
    }
}

class XConnectionTest extends TestCase
{
    private XConnection $connection;

    protected function setUp(): void
    {
        $this->connection = new XConnection([
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'database' => 'test_db',
            'username' => 'root',
            'password' => '',
        ]);
    }

    public function testBuildDsn(): void
    {
        $this->assertInstanceOf(XConnection::class, $this->connection);
    }

    public function testValidateConfigThrowsExceptionForMissingKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Config key 'host' is required");

        new XConnection(['driver' => 'mysql', 'database' => 'test']);
    }

    public function testConnectionConfig(): void
    {
        $config = $this->connection->getConfig();

        $this->assertEquals('localhost', $config['host']);
        $this->assertEquals('test_db', $config['database']);
        $this->assertEquals(3306, $config['port']);
        $this->assertEquals('utf8mb4', $config['charset']);
    }
}

class XQueryBuilderTest extends TestCase
{
    private XConnection $connection;
    private XQueryBuilder $qb;

    protected function setUp(): void
    {
        $this->connection = new XConnection([
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'database' => 'test_db',
            'username' => 'root',
            'password' => '',
        ]);
        $this->qb = $this->connection->createQueryBuilder();
    }

    public function testSelect(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->getSQL();

        $this->assertEquals('SELECT * FROM users', $sql);
    }

    public function testSelectWithColumns(): void
    {
        $sql = $this->qb
            ->select(['id', 'name', 'email'])
            ->from('users')
            ->getSQL();

        $this->assertEquals('SELECT id, name, email FROM users', $sql);
    }

    public function testWhere(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->where('email', 'test@example.com')
            ->getSQL();

        $this->assertStringContainsString('WHERE email = :email', $sql);
        $this->assertEquals('test@example.com', $this->qb->getParameters()['email']);
    }

    public function testWhereIn(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->whereIn('id', [1, 2, 3])
            ->getSQL();

        $this->assertStringContainsString('WHERE id IN (:id_in_0, :id_in_1, :id_in_2)', $sql);
    }

    public function testWhereNull(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->whereNull('deleted_at')
            ->getSQL();

        $this->assertEquals('SELECT * FROM users WHERE deleted_at IS NULL', $sql);
    }

    public function testWhereBetween(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->whereBetween('age', 18, 65)
            ->getSQL();

        $this->assertStringContainsString('WHERE age BETWEEN', $sql);
    }

    public function testWhereLike(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->whereLike('name', 'john')
            ->getSQL();

        $this->assertStringContainsString('WHERE name LIKE :name_like', $sql);
        $this->assertStringContainsString('%john%', $this->qb->getParameters()['name_like']);
    }

    public function testOrWhere(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->where('status', 'active')
            ->orWhere('role', 'admin')
            ->getSQL();

        $this->assertStringContainsString('OR role = :role', $sql);
    }

    public function testJoin(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->join('posts', 'users.id = posts.user_id', 'p')
            ->getSQL();

        $this->assertStringContainsString('INNER JOIN posts AS p ON users.id = posts.user_id', $sql);
    }

    public function testLeftJoin(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->leftJoin('posts', 'users.id = posts.user_id')
            ->getSQL();

        $this->assertStringContainsString('LEFT JOIN posts', $sql);
    }

    public function testOrderBy(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->orderBy('created_at', 'DESC')
            ->orderBy('name', 'ASC')
            ->getSQL();

        $this->assertStringContainsString('ORDER BY created_at DESC, name ASC', $sql);
    }

    public function testLimitAndOffset(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->limit(10)
            ->offset(20)
            ->getSQL();

        $this->assertStringContainsString('LIMIT 10 OFFSET 20', $sql);
    }

    public function testPage(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->page(3, 20)
            ->getSQL();

        $this->assertStringContainsString('LIMIT 20 OFFSET 40', $sql);
    }

    public function testGroupBy(): void
    {
        $sql = $this->qb
            ->select('role, COUNT(*)')
            ->from('users')
            ->groupBy('role')
            ->getSQL();

        $this->assertStringContainsString('GROUP BY role', $sql);
    }

    public function testComplexQuery(): void
    {
        $sql = $this->qb
            ->select(['u.id', 'u.name', 'u.email', 'COUNT(p.id) as post_count'])
            ->from('users', 'u')
            ->leftJoin('posts', 'u.id = p.user_id', 'p')
            ->where('u.status', 'active')
            ->orWhere('u.role', 'admin')
            ->groupBy('u.id')
            ->having('COUNT(p.id) > ?', 5)
            ->orderBy('post_count', 'DESC')
            ->limit(10)
            ->getSQL();

        $this->assertStringContainsString('SELECT u.id, u.name, u.email, COUNT(p.id) as post_count', $sql);
        $this->assertStringContainsString('FROM users AS u', $sql);
        $this->assertStringContainsString('LEFT JOIN posts AS p', $sql);
        $this->assertStringContainsString('WHERE u.status = :status', $sql);
        $this->assertStringContainsString('OR u.role = :role', $sql);
        $this->assertStringContainsString('GROUP BY u.id', $sql);
        $this->assertStringContainsString('ORDER BY post_count DESC', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testReset(): void
    {
        $this->qb
            ->select('*')
            ->from('users')
            ->where('id', 1)
            ->limit(10);

        $this->qb->reset();

        $sql = $this->qb
            ->select('*')
            ->from('posts')
            ->getSQL();

        $this->assertEquals('SELECT * FROM posts', $sql);
        $this->assertEmpty($this->qb->getParameters());
    }

    public function testToString(): void
    {
        $sql = $this->qb
            ->select('*')
            ->from('users')
            ->where('id', 1)
            ->getSQL();

        $this->assertEquals($this->qb->__toString(), $sql);
    }
}

class XEntityMapTest extends TestCase
{
    public function testEntityMetadata(): void
    {
        $map = \Xpress\Orm\Entity\XEntityMap::get(TestUser::class);

        $this->assertEquals('test_users', $map->getTable());
        $this->assertEquals('id', $map->getPrimaryKey());
        $this->assertEquals('id', $map->getPrimaryKeyColumn());
        $this->assertTrue($map->isPrimaryKeyAutoIncrement());
    }

    public function testColumnMapping(): void
    {
        $map = \Xpress\Orm\Entity\XEntityMap::get(TestUser::class);

        $this->assertEquals('name', $map->getColumnName('name'));
        $this->assertEquals('email', $map->getColumnName('email'));
        $this->assertEquals('name', $map->getPropertyName('name'));
    }

    public function testColumnProperties(): void
    {
        $map = \Xpress\Orm\Entity\XEntityMap::get(TestUser::class);

        $columns = $map->getColumns();

        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('email', $columns);
        $this->assertArrayHasKey('age', $columns);

        $this->assertEquals('varchar', $columns['name']['type']);
        $this->assertEquals(100, $columns['name']['length']);
        $this->assertFalse($columns['name']['nullable']);

        $this->assertTrue($columns['email']['unique']);
    }

    public function testPropertyExists(): void
    {
        $map = \Xpress\Orm\Entity\XEntityMap::get(TestUser::class);

        $this->assertTrue($map->hasProperty('name'));
        $this->assertTrue($map->hasProperty('email'));
        $this->assertFalse($map->hasProperty('nonexistent'));
    }

    public function testCache(): void
    {
        $map1 = \Xpress\Orm\Entity\XEntityMap::get(TestUser::class);
        $map2 = \Xpress\Orm\Entity\XEntityMap::get(TestUser::class);

        $this->assertSame($map1, $map2);
    }

    public function testClearCache(): void
    {
        $map1 = \Xpress\Orm\Entity\XEntityMap::get(TestUser::class);
        \Xpress\Orm\Entity\XEntityMap::clearCache();
        $map2 = \Xpress\Orm\Entity\XEntityMap::get(TestUser::class);

        $this->assertNotSame($map1, $map2);
    }
}

class XHydratorTest extends TestCase
{
    public function testHydrate(): void
    {
        $connection = new XConnection([
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'database' => 'test_db',
            'username' => 'root',
            'password' => '',
        ]);

        $em = new \Xpress\Orm\Entity\XEntityManager($connection);
        $hydrator = new \Xpress\Orm\Hydrator\XHydrator($em);

        $data = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ];

        $user = $hydrator->hydrate(TestUser::class, $data);

        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertEquals(1, $user->getId());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertEquals(30, $user->getAge());
    }

    public function testHydrateAll(): void
    {
        $connection = new XConnection([
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'database' => 'test_db',
            'username' => 'root',
            'password' => '',
        ]);

        $em = new \Xpress\Orm\Entity\XEntityManager($connection);
        $hydrator = new \Xpress\Orm\Hydrator\XHydrator($em);

        $data = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => 30],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com', 'age' => 25],
        ];

        $users = $hydrator->hydrateAll(TestUser::class, $data);

        $this->assertCount(2, $users);
        $this->assertInstanceOf(TestUser::class, $users[0]);
        $this->assertInstanceOf(TestUser::class, $users[1]);
        $this->assertEquals('John', $users[0]->getName());
        $this->assertEquals('Jane', $users[1]->getName());
    }

    public function testExtract(): void
    {
        $connection = new XConnection([
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'database' => 'test_db',
            'username' => 'root',
            'password' => '',
        ]);

        $em = new \Xpress\Orm\Entity\XEntityManager($connection);
        $hydrator = new \Xpress\Orm\Hydrator\XHydrator($em);

        $user = new TestUser();
        $user->setId(1);
        $user->setName('John Doe');
        $user->setEmail('john@example.com');
        $user->setAge(30);

        $data = $hydrator->extract($user);

        $this->assertEquals(1, $data['id']);
        $this->assertEquals('John Doe', $data['name']);
        $this->assertEquals('john@example.com', $data['email']);
        $this->assertEquals(30, $data['age']);
    }

    public function testTransformValue(): void
    {
        $connection = new XConnection([
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'database' => 'test_db',
            'username' => 'root',
            'password' => '',
        ]);

        $em = new \Xpress\Orm\Entity\XEntityManager($connection);
        $hydrator = new \Xpress\Orm\Hydrator\XHydrator($em);

        $data = [
            'id' => '1',
            'name' => 'John',
            'email' => 'john@example.com',
            'age' => '30',
        ];

        $user = $hydrator->hydrate(TestUser::class, $data);

        $this->assertIsInt($user->getId());
        $this->assertIsInt($user->getAge());
        $this->assertEquals(1, $user->getId());
        $this->assertEquals(30, $user->getAge());
    }
}
