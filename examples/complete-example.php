<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\{User, Post, Category, Comment, Tag, Role};
use App\Repositories\{UserRepository, PostRepository};
use Xpress\Orm\Connection\XConnection;
use Xpress\Orm\Entity\XEntityManager;
use Xpress\Orm\Schema\XAutoMigrate;

echo "=== Xpress ORM - Ejemplo Completo ===\n\n";

$connection = new XConnection([
    'driver' => 'pdo_mysql',
    'host' => 'localhost',
    'database' => 'xpress_demo',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
]);

$em = new XEntityManager($connection);

echo "1. Auto-migrate: Sincronizando schema...\n";

$migrate = new XAutoMigrate($connection);
$migrate->setVerbose(true);
$logs = $migrate->updateSchema([User::class, Post::class, Category::class, Comment::class, Tag::class, Role::class]);

echo "\n";

echo "2. CRUD básico con EntityManager\n";

$user = new User();
$user->setName('Juan Pérez');
$user->setEmail('juan@ejemplo.com');
$user->setRole('admin');
$user->hashPassword('secret123');

$em->save($user);
echo "   ✓ Usuario creado: ID={$user->getId()}, Email={$user->getEmail()}\n";

$user->setName('Juan Pérez Actualizado');
$em->save($user);
echo "   ✓ Usuario actualizado: Nombre={$user->getName()}\n";

$foundUser = $em->find(User::class, $user->getId());
echo "   ✓ Usuario encontrado: " . ($foundUser ? $foundUser->getName() : 'N/A') . "\n";

$em->delete($foundUser);
echo "   ✓ Usuario eliminado (soft delete)\n\n";

echo "3. Repositorios personalizados\n";

$userRepo = $em->getRepository(UserRepository::class);

$admin = new User();
$admin->setName('Admin User');
$admin->setEmail('admin@ejemplo.com');
$admin->setRole('admin');
$admin->hashPassword('admin123');
$userRepo->save($admin);
echo "   ✓ Admin creado\n";

$admins = $userRepo->findAdmins();
echo "   ✓ Admins encontrados: " . count($admins) . "\n";

$exists = $userRepo->existsByEmail('admin@ejemplo.com');
echo "   ✓ Existe email: " . ($exists ? 'Sí' : 'No') . "\n\n";

echo "4. Query Builder\n";

$qb = $em->createQuery(User::class);

$users = $qb
    ->select('*')
    ->from('users')
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->getResult();

echo "   ✓ Usuarios activos: " . count($users) . "\n\n";

echo "5. Relaciones y Joins\n";

$category = new Category();
$category->setName('Tecnología');
$category->setDescription('Artículos sobre tecnología');
$em->save($category);
echo "   ✓ Categoría creada: {$category->getName()}\n";

$post = new Post();
$post->setTitle('Introducción a Xpress ORM');
$post->setContent('<p>Este es un artículo sobre el ORM...</p>');
$post->setAuthor($admin);
$post->setCategory($category);
$post->publish();
$em->save($post);
echo "   ✓ Post publicado: {$post->getTitle()}\n";

$post->incrementViews();
$em->save($post);
echo "   ✓ Vistas del post: {$post->getViews()}\n\n";

echo "6. Paginación\n";

$postRepo = $em->getRepository(PostRepository::class);
$pagination = $postRepo->paginate(1, 10, ['status' => 'published']);

echo "   ✓ Total posts: {$pagination['total']}\n";
echo "   ✓ Posts en página: " . count($pagination['items']) . "\n";
echo "   ✓ Total páginas: {$pagination['pages']}\n";
echo "   ✓ Tiene siguiente: " . ($pagination['has_next'] ? 'Sí' : 'No') . "\n\n";

echo "7. Búsqueda\n";

$searchResults = $userRepo->searchUsers('Juan');
echo "   ✓ Resultados de búsqueda: " . count($searchResults) . "\n\n";

echo "8. Transacciones\n";

try {
    $em->beginTransaction();

    $user1 = new User();
    $user1->setName('Usuario Transacción 1');
    $user1->setEmail('trans1@ejemplo.com');
    $user1->hashPassword('pass');
    $em->save($user1);

    $user2 = new User();
    $user2->setName('Usuario Transacción 2');
    $user2->setEmail('trans2@ejemplo.com');
    $user2->hashPassword('pass');
    $em->save($user2);

    $em->commit();
    echo "   ✓ Transacción completada exitosamente\n";
} catch (\Exception $e) {
    $em->rollback();
    echo "   ✗ Transacción revertida: {$e->getMessage()}\n";
}

echo "\n9. Conteo y estadísticas\n";

$totalUsers = $em->count(User::class);
echo "   ✓ Total usuarios: {$totalUsers}\n";

$totalPosts = $em->count(Post::class);
echo "   ✓ Total posts: {$totalPosts}\n";

$usersByRole = $userRepo->countByRole();
echo "   ✓ Usuarios por rol:\n";
foreach ($usersByRole as $role => $count) {
    echo "     - {$role}: {$count}\n";
}

echo "\n10. Extracción de datos\n";

$data = $em->extract($admin);
echo "   ✓ Datos del admin:\n";
echo "     - Nombre: {$data['name']}\n";
echo "     - Email: {$data['email']}\n";
echo "     - Rol: {$data['role']}\n";

echo "\n=== Ejemplo completado exitosamente ===\n";
