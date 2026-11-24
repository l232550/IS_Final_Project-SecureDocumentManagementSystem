<?php
/**
 * Creates CONTROLLED TEST ACCOUNTS for demonstrations (development only). The shared test password below is
 * intentionally public and is not a real secret. Never run this against production.
 */
require __DIR__ . '/../config/bootstrap.php';
if (PHP_SAPI !== 'cli') { exit(1); }
if (Config::isProduction()) { fwrite(STDERR, "Refusing to seed in production.\n"); exit(1); }

const TEST_PASSWORD = 'TestPassword#2026';
$accounts = [['admin_test', 'admin@test.local', 'admin'], ['alice', 'alice@test.local', 'user'],
             ['bob', 'bob@test.local', 'user'], ['carol', 'carol@test.local', 'user']];
foreach ($accounts as [$u, $e, $role]) {
    if (Container::users()->usernameOrEmailExists($u, $e)) { echo "exists: $u\n"; continue; }
    $id = Container::users()->create($u, $e, AuthService::hashPassword(TEST_PASSWORD), $role);
    Container::audit()->log('test_user_seeded', 'success', $id, null, ['role' => $role]);
    echo "created: $u ($role)\n";
}
echo "Test password for all seeded accounts: " . TEST_PASSWORD . "\n";
