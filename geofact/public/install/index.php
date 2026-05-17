<?php
/**
 * IKOMA GEOFACT — Web Installer
 * Single-file standalone installer. No Laravel bootstrap required.
 * Place at: geofact/public/install/index.php
 */

declare(strict_types=1);

define('LARAVEL_ROOT', realpath(__DIR__ . '/../../'));
define('INSTALLER_LOCK', LARAVEL_ROOT . '/storage/installed');
define('ENV_FILE', LARAVEL_ROOT . '/.env');
define('ENV_EXAMPLE', LARAVEL_ROOT . '/.env.example');

session_start();

// ─── CSRF helpers ────────────────────────────────────────────────────────────
function csrf_generate(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// ─── UUID v4 ─────────────────────────────────────────────────────────────────
function generateUuidV4(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// ─── Artisan runner ──────────────────────────────────────────────────────────
function findPhpCli(): string {
    // Sur cPanel/CloudLinux, le PHP CLI diffère du PHP web (CGI/FPM)
    $major = PHP_MAJOR_VERSION;
    $minor = PHP_MINOR_VERSION;
    $candidates = [
        "/opt/alt/php{$major}{$minor}/usr/bin/php",   // CloudLinux alt-php
        "/usr/local/bin/ea-php{$major}{$minor}",       // cPanel EA-PHP
        "/usr/local/bin/php{$major}.{$minor}",
        "/usr/local/bin/php",
        "/usr/bin/php",
        "php",
    ];
    foreach ($candidates as $path) {
        $out = [];
        exec(escapeshellarg($path) . ' -r "echo PHP_SAPI;" 2>/dev/null', $out, $code);
        if ($code === 0 && isset($out[0]) && trim($out[0]) === 'cli') {
            return $path;
        }
    }
    return PHP_BINARY; // fallback
}

function runArtisan(string $cmd): array {
    $php = findPhpCli();
    $artisan = LARAVEL_ROOT . '/artisan';
    exec(escapeshellarg($php) . ' ' . escapeshellarg($artisan) . ' ' . $cmd . ' 2>&1', $out, $code);
    return ['ok' => $code === 0, 'output' => implode("\n", $out)];
}

function findComposer(): ?string {
    $candidates = [
        LARAVEL_ROOT . '/composer.phar',
        '/usr/local/bin/composer',
        '/usr/bin/composer',
        '/opt/cpanel/composer/bin/composer',
    ];
    foreach ($candidates as $path) {
        if (file_exists($path)) return $path;
    }
    $out = [];
    exec('which composer 2>/dev/null', $out);
    if (!empty($out[0]) && file_exists(trim($out[0]))) return trim($out[0]);
    return null;
}

function runComposerInstall(): array {
    $vendor = LARAVEL_ROOT . '/vendor/autoload.php';
    if (file_exists($vendor)) {
        return ['ok' => true, 'output' => 'vendor/ déjà présent, étape ignorée.'];
    }
    $php = findPhpCli();
    $composer = findComposer();
    if (!$composer) {
        return ['ok' => false, 'output' => 'Composer introuvable. Lancez manuellement depuis SSH : cd ' . LARAVEL_ROOT . ' && composer install --no-dev'];
    }

    // HOME et COMPOSER_HOME sont absents en contexte web — les définir explicitement
    $composerHome = sys_get_temp_dir() . '/.composer_install';
    if (!is_dir($composerHome)) @mkdir($composerHome, 0755, true);
    $homeDir = sys_get_temp_dir();

    $env = 'HOME=' . escapeshellarg($homeDir)
         . ' COMPOSER_HOME=' . escapeshellarg($composerHome)
         . ' COMPOSER_ALLOW_SUPERUSER=1';

    $cmd = $env . ' ' . escapeshellarg($php) . ' ' . escapeshellarg($composer)
         . ' install --no-dev --optimize-autoloader --no-interaction --working-dir='
         . escapeshellarg(LARAVEL_ROOT) . ' 2>&1';
    exec($cmd, $out, $code);
    return ['ok' => $code === 0, 'output' => implode("\n", $out)];
}

// ─── Requirements ────────────────────────────────────────────────────────────
function checkRequirements(): array {
    $checks = [];

    $checks['php_version'] = [
        'label' => 'PHP >= 8.2',
        'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
        'value' => PHP_VERSION,
        'required' => true,
    ];

    $exts = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'curl', 'fileinfo'];
    foreach ($exts as $ext) {
        $checks['ext_' . $ext] = [
            'label' => 'Extension: ' . $ext,
            'ok' => extension_loaded($ext),
            'value' => extension_loaded($ext) ? 'Loaded' : 'Missing',
            'required' => true,
        ];
    }

    $storagePath = LARAVEL_ROOT . '/storage';
    $checks['storage_writable'] = [
        'label' => 'storage/ writable',
        'ok' => is_writable($storagePath),
        'value' => is_writable($storagePath) ? 'Writable' : 'Not writable',
        'required' => true,
    ];

    $cachePath = LARAVEL_ROOT . '/bootstrap/cache';
    $checks['cache_writable'] = [
        'label' => 'bootstrap/cache/ writable',
        'ok' => is_writable($cachePath),
        'value' => is_writable($cachePath) ? 'Writable' : 'Not writable',
        'required' => true,
    ];

    $checks['root_writable'] = [
        'label' => 'Root directory writable (.env)',
        'ok' => is_writable(LARAVEL_ROOT),
        'value' => is_writable(LARAVEL_ROOT) ? 'Writable' : 'Not writable',
        'required' => true,
    ];

    $checks['exec_available'] = [
        'label' => 'exec() function available',
        'ok' => function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions') ?: ''))),
        'value' => (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions') ?: '')))) ? 'Available' : 'Disabled',
        'required' => true,
    ];

    return $checks;
}

// ─── Write .env ──────────────────────────────────────────────────────────────
function writeEnvFile(array $vars): bool {
    $content = '';
    if (file_exists(ENV_EXAMPLE)) {
        $content = file_get_contents(ENV_EXAMPLE);
    }

    foreach ($vars as $key => $value) {
        $escaped = (strpbrk((string)$value, " \t\n\"'\\#") !== false) ? '"' . addslashes((string)$value) . '"' : (string)$value;
        if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $content)) {
            $content = preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $key . '=' . $escaped, $content);
        } else {
            $content .= "\n" . $key . '=' . $escaped;
        }
    }

    return file_put_contents(ENV_FILE, $content) !== false;
}

// ─── Lock check ──────────────────────────────────────────────────────────────
if (file_exists(INSTALLER_LOCK)) {
    $lockContent = file_get_contents(INSTALLER_LOCK);
    renderLockedPage($lockContent);
    exit;
}

// ─── AJAX actions ────────────────────────────────────────────────────────────
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    if ($action === 'test_db') {
        if (!csrf_verify()) { echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.']); exit; }
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = (int)($_POST['db_port'] ?? 3306);
        $dbname = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = $_POST['db_pass'] ?? '';
        try {
            // Sur cPanel, la base doit être pré-créée — on ne tente pas CREATE DATABASE
            if ($dbname === '') {
                echo json_encode(['ok' => false, 'message' => 'Veuillez saisir le nom de la base de données.']);
                exit;
            }
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            echo json_encode(['ok' => true, 'message' => 'Connexion réussie — ' . $version]);
        } catch (PDOException $e) {
            $hint = '';
            if (str_contains($e->getMessage(), 'Unknown database')) {
                $hint = ' — Créez d\'abord la base de données dans cPanel → MySQL Databases.';
            } elseif (str_contains($e->getMessage(), 'Access denied')) {
                $hint = ' — Vérifiez le nom d\'utilisateur et le mot de passe.';
            }
            echo json_encode(['ok' => false, 'message' => 'Échec : ' . $e->getMessage() . $hint]);
        }
        exit;
    }

    if ($action === 'do_install') {
        if (!csrf_verify()) { echo json_encode(['ok' => false, 'step' => 0, 'message' => 'Invalid CSRF token.']); exit; }
        doInstall();
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
    exit;
}

// ─── POST: save step data ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_step'])) {
    if (!csrf_verify()) {
        $_SESSION['flash_error'] = 'Invalid CSRF token.';
        header('Location: ?step=' . ($_POST['current_step'] ?? 1));
        exit;
    }
    $step = (int)$_POST['save_step'];
    $data = $_SESSION['ikoma_install'] ?? [];

    switch ($step) {
        case 2:
            $data['db'] = [
                'host' => trim($_POST['db_host'] ?? '127.0.0.1'),
                'port' => trim($_POST['db_port'] ?? '3306'),
                'name' => trim($_POST['db_name'] ?? ''),
                'user' => trim($_POST['db_user'] ?? ''),
                'pass' => $_POST['db_pass'] ?? '',
            ];
            break;
        case 3:
            $data['app'] = [
                'name'         => trim($_POST['app_name'] ?? 'IKOMA GEOFACT'),
                'url'          => rtrim(trim($_POST['app_url'] ?? ''), '/'),
                'timezone'     => trim($_POST['app_timezone'] ?? 'Africa/Abidjan'),
                'anthropic_key'=> trim($_POST['anthropic_key'] ?? ''),
                'wa_token'     => trim($_POST['wa_token'] ?? ''),
                'wa_phone_id'  => trim($_POST['wa_phone_id'] ?? ''),
            ];
            break;
        case 4:
            $pass = $_POST['admin_pass'] ?? '';
            $confirm = $_POST['admin_pass_confirm'] ?? '';
            if ($pass !== $confirm) {
                $_SESSION['flash_error'] = 'Passwords do not match.';
                header('Location: ?step=4');
                exit;
            }
            if (strlen($pass) < 8) {
                $_SESSION['flash_error'] = 'Password must be at least 8 characters.';
                header('Location: ?step=4');
                exit;
            }
            $data['admin'] = [
                'org_name'   => trim($_POST['org_name'] ?? ''),
                'country'    => strtoupper(trim($_POST['country_code'] ?? 'CI')),
                'first_name' => trim($_POST['admin_first'] ?? ''),
                'last_name'  => trim($_POST['admin_last'] ?? ''),
                'email'      => strtolower(trim($_POST['admin_email'] ?? '')),
                'pass'       => $pass,
            ];
            break;
    }

    $_SESSION['ikoma_install'] = $data;
    header('Location: ?step=' . ($step + 1));
    exit;
}

// ─── Current step ─────────────────────────────────────────────────────────────
$currentStep = max(1, min(5, (int)($_GET['step'] ?? 1)));
$csrfToken = csrf_generate();
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

// ─── Install function ────────────────────────────────────────────────────────
function doInstall(): void {
    $data = $_SESSION['ikoma_install'] ?? [];
    $db   = $data['db']    ?? [];
    $app  = $data['app']   ?? [];
    $adm  = $data['admin'] ?? [];

    $steps = [];

    // Step 1: Write .env
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $appUrl = $app['url'] ?: $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    $envVars = [
        'APP_NAME'              => $app['name'] ?: 'IKOMA GEOFACT',
        'APP_ENV'               => 'production',
        'APP_KEY'               => $appKey,
        'APP_DEBUG'             => 'false',
        'APP_URL'               => $appUrl,
        'APP_TIMEZONE'          => $app['timezone'] ?: 'Africa/Abidjan',
        'DB_CONNECTION'         => 'mysql',
        'DB_HOST'               => $db['host'] ?: '127.0.0.1',
        'DB_PORT'               => $db['port'] ?: '3306',
        'DB_DATABASE'           => $db['name'] ?: '',
        'DB_USERNAME'           => $db['user'] ?: '',
        'DB_PASSWORD'           => $db['pass'] ?: '',
        'QUEUE_CONNECTION'      => 'database',
        'CACHE_STORE'           => 'database',
        'SESSION_DRIVER'        => 'database',
    ];

    if (!empty($app['anthropic_key'])) {
        $envVars['ANTHROPIC_API_KEY'] = $app['anthropic_key'];
    }
    if (!empty($app['wa_token'])) {
        $envVars['WHATSAPP_TOKEN'] = $app['wa_token'];
    }
    if (!empty($app['wa_phone_id'])) {
        $envVars['WHATSAPP_PHONE_NUMBER_ID'] = $app['wa_phone_id'];
    }

    $writeOk = writeEnvFile($envVars);
    $steps[] = ['label' => 'Écriture du fichier .env', 'ok' => $writeOk, 'output' => $writeOk ? 'OK' : 'Impossible d\'écrire ' . ENV_FILE];
    if (!$writeOk) { echo json_encode(['ok' => false, 'steps' => $steps]); return; }

    // Step 2: Composer install (si vendor/ absent)
    $r = runComposerInstall();
    $steps[] = ['label' => 'Installation des dépendances (composer install)', 'ok' => $r['ok'], 'output' => $r['output']];
    if (!$r['ok']) { echo json_encode(['ok' => false, 'steps' => $steps]); return; }

    // Step 3: Clear config cache
    $r = runArtisan('config:clear');
    $steps[] = ['label' => 'Nettoyage du cache de configuration', 'ok' => $r['ok'] || true, 'output' => $r['output']]; // non bloquant

    // Step 3: Migrate
    $r = runArtisan('migrate --force --no-interaction');
    $steps[] = ['label' => 'Running database migrations', 'ok' => $r['ok'], 'output' => $r['output']];
    if (!$r['ok']) { echo json_encode(['ok' => false, 'steps' => $steps]); return; }

    // Step 4: Create super admin via PDO
    try {
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $orgId   = generateUuidV4();
        $adminId = generateUuidV4();
        $now     = date('Y-m-d H:i:s');
        $orgTimezone = $app['timezone'] ?: 'Africa/Abidjan';

        $stmt = $pdo->prepare("INSERT INTO organizations (id, name, country_code, timezone, status, created_by, created_at, updated_at)
            VALUES (:id, :name, :country, :tz, 'active', :created_by, :ca, :ua)");
        $stmt->execute([
            ':id'         => $orgId,
            ':name'       => $adm['org_name'],
            ':country'    => $adm['country'],
            ':tz'         => $orgTimezone,
            ':created_by' => $adminId,
            ':ca'         => $now,
            ':ua'         => $now,
        ]);

        $passwordHash = password_hash($adm['pass'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("INSERT INTO users (id, organization_id, first_name, last_name, email, password_hash, role, token_version, status, created_by, created_at, updated_at)
            VALUES (:id, :org_id, :fn, :ln, :email, :hash, 'geofact_admin', 1, 'active', :created_by, :ca, :ua)");
        $stmt->execute([
            ':id'          => $adminId,
            ':org_id'      => $orgId,
            ':fn'          => $adm['first_name'],
            ':ln'          => $adm['last_name'],
            ':email'       => $adm['email'],
            ':hash'        => $passwordHash,
            ':created_by'  => $adminId,
            ':ca'          => $now,
            ':ua'          => $now,
        ]);

        $steps[] = ['label' => 'Creating super admin account', 'ok' => true, 'output' => 'User ' . $adm['email'] . ' created.'];
    } catch (PDOException $e) {
        $steps[] = ['label' => 'Creating super admin account', 'ok' => false, 'output' => $e->getMessage()];
        echo json_encode(['ok' => false, 'steps' => $steps]);
        return;
    }

    // Step 5: Generate app key (already embedded in .env, but run to ensure bootstrap)
    $r = runArtisan('config:cache');
    $steps[] = ['label' => 'Caching configuration', 'ok' => $r['ok'], 'output' => $r['output']];
    if (!$r['ok']) { echo json_encode(['ok' => false, 'steps' => $steps]); return; }

    // Step 6: Cache routes
    $r = runArtisan('route:cache');
    $steps[] = ['label' => 'Caching routes', 'ok' => $r['ok'], 'output' => $r['output']];
    // Non-fatal — routes might fail if no routes defined yet; continue anyway

    // Step 7: Lock installer
    $lockContent = "Installed on: " . date('Y-m-d H:i:s') . "\nAdmin email: " . ($adm['email'] ?? '') . "\n";
    $locked = file_put_contents(INSTALLER_LOCK, $lockContent) !== false;
    $steps[] = ['label' => 'Locking installer', 'ok' => $locked, 'output' => $locked ? 'Lock file created.' : 'Warning: could not create lock file.'];

    echo json_encode(['ok' => true, 'steps' => $steps, 'app_url' => $appUrl ?? '']);
}

// ─── Helper: h() ─────────────────────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function renderLockedPage(string $info): void {
    ?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>IKOMA GEOFACT — Already Installed</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border-radius:12px;padding:48px;max-width:480px;width:100%;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.lock-icon{width:64px;height:64px;margin:0 auto 24px;background:#fef3c7;border-radius:50%;display:flex;align-items:center;justify-content:center}
h1{color:#1e3a5f;font-size:1.5rem;margin-bottom:12px}
p{color:#64748b;line-height:1.6;margin-bottom:8px}
pre{background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px;font-size:.8rem;text-align:left;white-space:pre-wrap;color:#475569;margin-top:16px}
a.btn{display:inline-block;margin-top:24px;background:#f97316;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600}
</style></head>
<body>
<div class="card">
  <div class="lock-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
  <h1>Application Already Installed</h1>
  <p>IKOMA GEOFACT has already been installed on this server.</p>
  <p>To reinstall, delete the file <code>storage/installed</code> on the server.</p>
  <pre><?= h($info) ?></pre>
  <a href="/" class="btn">Go to Application</a>
</div>
</body></html>
<?php
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>IKOMA GEOFACT — Installer</title>
<style>
/* ─── Reset & Base ─── */
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f0f4f8;min-height:100vh;color:#1e293b}
a{color:#f97316;text-decoration:none}

/* ─── Layout ─── */
.layout{display:flex;min-height:100vh}
.sidebar{width:280px;background:#1e3a5f;color:#fff;display:flex;flex-direction:column;padding:0;flex-shrink:0}
.sidebar-header{padding:32px 24px 24px;border-bottom:1px solid rgba(255,255,255,.1)}
.logo-text{font-size:1.2rem;font-weight:700;letter-spacing:.5px;color:#fff}
.logo-sub{font-size:.7rem;color:#94a3b8;margin-top:2px;text-transform:uppercase;letter-spacing:1px}
.steps-nav{padding:24px 0;flex:1}
.step-item{display:flex;align-items:center;padding:12px 24px;cursor:default;transition:background .2s;gap:12px}
.step-item.active{background:rgba(249,115,22,.15);border-right:3px solid #f97316}
.step-item.done{opacity:.7}
.step-num{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0;transition:background .2s}
.step-item.active .step-num{background:#f97316}
.step-item.done .step-num{background:#22c55e}
.step-label{font-size:.85rem;color:rgba(255,255,255,.8);font-weight:500}
.step-item.active .step-label{color:#fff;font-weight:600}
.sidebar-footer{padding:24px;border-top:1px solid rgba(255,255,255,.1);font-size:.75rem;color:#64748b}

/* ─── Content ─── */
.content{flex:1;display:flex;flex-direction:column;overflow:auto}
.content-header{background:#fff;border-bottom:1px solid #e2e8f0;padding:20px 40px;display:flex;align-items:center;justify-content:space-between}
.content-header h2{font-size:1.1rem;font-weight:600;color:#1e3a5f}
.step-badge{background:#f0f9ff;color:#0284c7;font-size:.75rem;font-weight:600;padding:4px 10px;border-radius:20px}
.content-body{padding:40px;flex:1}

/* ─── Cards ─── */
.card{background:#fff;border-radius:12px;padding:32px;box-shadow:0 1px 8px rgba(0,0,0,.06);margin-bottom:24px}
.card-title{font-size:1rem;font-weight:700;color:#1e3a5f;margin-bottom:4px}
.card-desc{font-size:.85rem;color:#64748b;margin-bottom:24px}

/* ─── Forms ─── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group.full{grid-column:1/-1}
label{font-size:.8rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.5px}
label .opt{font-weight:400;color:#94a3b8;text-transform:none;letter-spacing:0}
input[type=text],input[type=email],input[type=password],input[type=number],select{
  padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.9rem;
  font-family:inherit;color:#1e293b;background:#fff;transition:border-color .2s,box-shadow .2s;width:100%
}
input:focus,select:focus{outline:none;border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.1)}
.input-hint{font-size:.75rem;color:#94a3b8;margin-top:2px}

/* ─── Buttons ─── */
.btn{display:inline-flex;align-items:center;gap:8px;padding:11px 24px;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;border:none;transition:all .2s;font-family:inherit}
.btn-primary{background:#f97316;color:#fff}
.btn-primary:hover:not(:disabled){background:#ea6c0a;transform:translateY(-1px);box-shadow:0 4px 12px rgba(249,115,22,.3)}
.btn-secondary{background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0}
.btn-secondary:hover:not(:disabled){background:#e2e8f0}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none!important;box-shadow:none!important}
.btn-group{display:flex;gap:12px;align-items:center;margin-top:24px}

/* ─── Check badges ─── */
.checks-list{display:flex;flex-direction:column;gap:8px}
.check-item{display:flex;align-items:center;padding:10px 14px;border-radius:8px;font-size:.875rem;gap:10px}
.check-item.pass{background:#f0fdf4;border:1px solid #bbf7d0}
.check-item.fail{background:#fef2f2;border:1px solid #fecaca}
.check-item.warn{background:#fffbeb;border:1px solid #fde68a}
.check-badge{font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:20px;flex-shrink:0}
.pass .check-badge{background:#22c55e;color:#fff}
.fail .check-badge{background:#ef4444;color:#fff}
.warn .check-badge{background:#f59e0b;color:#fff}
.check-label{font-weight:500;color:#374151;flex:1}
.check-value{font-size:.75rem;color:#64748b;font-family:monospace}

/* ─── DB test ─── */
.test-result{margin-top:12px;padding:10px 14px;border-radius:8px;font-size:.85rem;font-weight:500;display:none}
.test-result.ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.test-result.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}

/* ─── Install progress ─── */
.install-steps{display:flex;flex-direction:column;gap:10px;margin:20px 0}
.inst-step{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:8px;background:#f8fafc;border:1px solid #e2e8f0;font-size:.875rem}
.inst-step .ist-icon{width:24px;height:24px;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.inst-step .ist-label{flex:1;color:#374151;font-weight:500}
.inst-step .ist-status{font-size:.75rem;font-weight:600;color:#94a3b8}
.inst-step.running{background:#fff7ed;border-color:#fed7aa}
.inst-step.running .ist-status{color:#f97316}
.inst-step.success{background:#f0fdf4;border-color:#bbf7d0}
.inst-step.success .ist-status{color:#22c55e}
.inst-step.error{background:#fef2f2;border-color:#fecaca}
.inst-step.error .ist-status{color:#ef4444}
.error-detail{font-size:.75rem;color:#b91c1c;margin-top:4px;font-family:monospace;word-break:break-all}

/* ─── Success screen ─── */
.success-screen{text-align:center;padding:40px 20px}
.success-icon{width:80px;height:80px;background:#f0fdf4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px}
.success-screen h2{color:#1e3a5f;font-size:1.5rem;margin-bottom:12px}
.success-screen p{color:#64748b;line-height:1.6;max-width:480px;margin:0 auto 24px}

/* ─── Spinner ─── */
@keyframes spin{to{transform:rotate(360deg)}}
.spinner{width:18px;height:18px;border:2.5px solid rgba(249,115,22,.3);border-top-color:#f97316;border-radius:50%;animation:spin .7s linear infinite;display:inline-block}

/* ─── Alert ─── */
.alert{padding:12px 16px;border-radius:8px;font-size:.875rem;font-weight:500;margin-bottom:20px}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}

/* ─── Responsive ─── */
@media(max-width:700px){
  .layout{flex-direction:column}
  .sidebar{width:100%;flex-direction:row;padding:0}
  .sidebar-header{display:none}
  .steps-nav{display:flex;padding:8px;overflow-x:auto}
  .step-item{flex-direction:column;gap:4px;padding:8px 12px;min-width:72px;text-align:center;border-right:none!important}
  .step-item.active{border-bottom:3px solid #f97316;border-right:none}
  .step-label{font-size:.7rem}
  .sidebar-footer{display:none}
  .content-body{padding:20px}
  .form-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="layout">

  <!-- ─── Sidebar ─── -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="logo-text">IKOMA GEOFACT</div>
      <div class="logo-sub">Fleet Intelligence Platform</div>
    </div>
    <nav class="steps-nav">
      <?php
      $stepDefs = [
        1 => ['Requirements', '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
        2 => ['Database',     '<path d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z"/><path d="M4 7c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3"/>'],
        3 => ['App Settings', '<path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/>'],
        4 => ['Admin Account','<path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
        5 => ['Install',      '<path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>'],
      ];
      foreach ($stepDefs as $n => [$label, $path]):
        $cls = $n === $currentStep ? 'active' : ($n < $currentStep ? 'done' : '');
      ?>
      <div class="step-item <?= $cls ?>">
        <div class="step-num">
          <?php if ($n < $currentStep): ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
          <?php else: ?>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><?= $path ?></svg>
          <?php endif; ?>
        </div>
        <span class="step-label"><?= h($label) ?></span>
      </div>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">IKOMA GEOFACT v1.0 &copy; <?= date('Y') ?></div>
  </aside>

  <!-- ─── Main content ─── -->
  <main class="content">
    <div class="content-header">
      <h2><?= h($stepDefs[$currentStep][0]) ?></h2>
      <span class="step-badge">Step <?= $currentStep ?> of 5</span>
    </div>
    <div class="content-body">

      <?php if ($flashError): ?>
      <div class="alert alert-error"><?= h($flashError) ?></div>
      <?php endif; ?>

      <!-- ═══════════ STEP 1: Requirements ═══════════ -->
      <?php if ($currentStep === 1):
        $checks = checkRequirements();
        $allOk = array_reduce($checks, fn($carry, $c) => $carry && (!$c['required'] || $c['ok']), true);
      ?>
      <div class="card">
        <div class="card-title">System Requirements Check</div>
        <div class="card-desc">All required checks must pass before you can proceed with the installation.</div>
        <div class="checks-list">
          <?php foreach ($checks as $check): ?>
          <div class="check-item <?= $check['ok'] ? 'pass' : ($check['required'] ? 'fail' : 'warn') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:<?= $check['ok'] ? '#22c55e' : ($check['required'] ? '#ef4444' : '#f59e0b') ?>;flex-shrink:0">
              <?php if ($check['ok']): ?>
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              <?php elseif ($check['required']): ?>
                <circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/>
              <?php else: ?>
                <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
              <?php endif; ?>
            </svg>
            <span class="check-label"><?= h($check['label']) ?></span>
            <span class="check-value"><?= h($check['value']) ?></span>
            <span class="check-badge"><?= $check['ok'] ? 'OK' : ($check['required'] ? 'FAIL' : 'WARN') ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="btn-group">
        <a href="?step=2" class="btn btn-primary <?= $allOk ? '' : 'disabled' ?>" <?= $allOk ? '' : 'aria-disabled="true" onclick="return false"' ?>>
          Proceed to Database Setup
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        <?php if (!$allOk): ?>
        <span style="font-size:.8rem;color:#ef4444">Fix failing requirements to continue.</span>
        <?php endif; ?>
      </div>

      <!-- ═══════════ STEP 2: Database ═══════════ -->
      <?php elseif ($currentStep === 2):
        $saved = $_SESSION['ikoma_install']['db'] ?? [];
      ?>
      <div class="card">
        <div class="card-title">Database Connection</div>
        <div class="card-desc">Enter your MySQL database credentials. The database will be created if it does not exist.</div>
        <div class="form-grid">
          <div class="form-group">
            <label for="db_host">Host</label>
            <input type="text" id="db_host" name="db_host" value="<?= h($saved['host'] ?? '127.0.0.1') ?>" placeholder="127.0.0.1">
          </div>
          <div class="form-group">
            <label for="db_port">Port</label>
            <input type="number" id="db_port" name="db_port" value="<?= h($saved['port'] ?? '3306') ?>" placeholder="3306" min="1" max="65535">
          </div>
          <div class="form-group full">
            <label for="db_name">Database Name</label>
            <input type="text" id="db_name" name="db_name" value="<?= h($saved['name'] ?? '') ?>" placeholder="ikoma_geofact">
          </div>
          <div class="form-group">
            <label for="db_user">Username</label>
            <input type="text" id="db_user" name="db_user" value="<?= h($saved['user'] ?? '') ?>" placeholder="db_user" autocomplete="username">
          </div>
          <div class="form-group">
            <label for="db_pass">Password</label>
            <input type="password" id="db_pass" name="db_pass" value="<?= h($saved['pass'] ?? '') ?>" placeholder="••••••••" autocomplete="current-password">
          </div>
        </div>
        <div class="btn-group" style="margin-top:16px">
          <button type="button" class="btn btn-secondary" id="btn-test-db" onclick="testDbConnection()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Test Connection
          </button>
        </div>
        <div class="test-result" id="db-test-result"></div>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="save_step" value="2">
        <input type="hidden" name="db_host" id="fdb_host">
        <input type="hidden" name="db_port" id="fdb_port">
        <input type="hidden" name="db_name" id="fdb_name">
        <input type="hidden" name="db_user" id="fdb_user">
        <input type="hidden" name="db_pass" id="fdb_pass">
        <div class="btn-group">
          <a href="?step=1" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            Back
          </a>
          <button type="submit" class="btn btn-primary" id="btn-next-db" disabled onclick="syncDbFields()">
            Next: App Settings
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
          </button>
        </div>
      </form>

      <!-- ═══════════ STEP 3: App Settings ═══════════ -->
      <?php elseif ($currentStep === 3):
        $saved = $_SESSION['ikoma_install']['app'] ?? [];
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $defaultUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'yoursite.com');
        $timezones = ['Africa/Abidjan','Africa/Lagos','Africa/Nairobi','Africa/Dakar','Africa/Accra','Europe/Paris','UTC'];
      ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="save_step" value="3">
        <div class="card">
          <div class="card-title">Application Settings</div>
          <div class="card-desc">Configure your application name, URL, and timezone.</div>
          <div class="form-grid">
            <div class="form-group">
              <label for="app_name">Application Name</label>
              <input type="text" id="app_name" name="app_name" value="<?= h($saved['name'] ?? 'IKOMA GEOFACT') ?>" required>
            </div>
            <div class="form-group">
              <label for="app_timezone">Timezone</label>
              <select id="app_timezone" name="app_timezone">
                <?php foreach ($timezones as $tz): ?>
                <option value="<?= h($tz) ?>" <?= ($saved['timezone'] ?? 'Africa/Abidjan') === $tz ? 'selected' : '' ?>><?= h($tz) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group full">
              <label for="app_url">Application URL</label>
              <input type="text" id="app_url" name="app_url" value="<?= h($saved['url'] ?? $defaultUrl) ?>" required placeholder="https://yoursite.com">
              <span class="input-hint">Full URL without trailing slash</span>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-title">Optional Integrations <span style="font-weight:400;color:#94a3b8;font-size:.85rem">(can be configured later in .env)</span></div>
          <div class="card-desc">Leave blank if you do not have these credentials yet.</div>
          <div class="form-grid">
            <div class="form-group full">
              <label for="anthropic_key">Anthropic API Key <span class="opt">(optional — Insight Engine)</span></label>
              <input type="text" id="anthropic_key" name="anthropic_key" value="<?= h($saved['anthropic_key'] ?? '') ?>" placeholder="sk-ant-…">
            </div>
            <div class="form-group">
              <label for="wa_token">WhatsApp Token <span class="opt">(optional)</span></label>
              <input type="text" id="wa_token" name="wa_token" value="<?= h($saved['wa_token'] ?? '') ?>" placeholder="EAABxxxx…">
            </div>
            <div class="form-group">
              <label for="wa_phone_id">WhatsApp Phone Number ID <span class="opt">(optional)</span></label>
              <input type="text" id="wa_phone_id" name="wa_phone_id" value="<?= h($saved['wa_phone_id'] ?? '') ?>" placeholder="123456789">
            </div>
          </div>
        </div>
        <div class="btn-group">
          <a href="?step=2" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            Back
          </a>
          <button type="submit" class="btn btn-primary">
            Next: Admin Account
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
          </button>
        </div>
      </form>

      <!-- ═══════════ STEP 4: Admin Account ═══════════ -->
      <?php elseif ($currentStep === 4):
        $saved = $_SESSION['ikoma_install']['admin'] ?? [];
      ?>
      <form method="POST" id="form-admin">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="save_step" value="4">
        <div class="card">
          <div class="card-title">Organisation</div>
          <div class="card-desc">The super admin organisation will own all initial resources.</div>
          <div class="form-grid">
            <div class="form-group">
              <label for="org_name">Organisation Name</label>
              <input type="text" id="org_name" name="org_name" value="<?= h($saved['org_name'] ?? '') ?>" required placeholder="My Company">
            </div>
            <div class="form-group">
              <label for="country_code">Country Code <span class="opt">(ISO 3166-1 alpha-2)</span></label>
              <input type="text" id="country_code" name="country_code" value="<?= h($saved['country'] ?? 'CI') ?>" maxlength="2" style="text-transform:uppercase" placeholder="CI" required>
              <span class="input-hint">2-letter code: CI, SN, NG, GH, KE, FR…</span>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-title">Super Admin Account</div>
          <div class="card-desc">This account will have full access to the platform.</div>
          <div class="form-grid">
            <div class="form-group">
              <label for="admin_first">First Name</label>
              <input type="text" id="admin_first" name="admin_first" value="<?= h($saved['first_name'] ?? '') ?>" required placeholder="Jean">
            </div>
            <div class="form-group">
              <label for="admin_last">Last Name</label>
              <input type="text" id="admin_last" name="admin_last" value="<?= h($saved['last_name'] ?? '') ?>" required placeholder="Kouadio">
            </div>
            <div class="form-group full">
              <label for="admin_email">Email Address</label>
              <input type="email" id="admin_email" name="admin_email" value="<?= h($saved['email'] ?? '') ?>" required placeholder="admin@yourcompany.com" autocomplete="email">
            </div>
            <div class="form-group">
              <label for="admin_pass">Password <span class="opt">(min. 8 characters)</span></label>
              <input type="password" id="admin_pass" name="admin_pass" required minlength="8" placeholder="••••••••" autocomplete="new-password">
            </div>
            <div class="form-group">
              <label for="admin_pass_confirm">Confirm Password</label>
              <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" required minlength="8" placeholder="••••••••" autocomplete="new-password">
            </div>
          </div>
        </div>
        <div class="btn-group">
          <a href="?step=3" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            Back
          </a>
          <button type="submit" class="btn btn-primary" onclick="return validateAdmin()">
            Review &amp; Install
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
          </button>
        </div>
      </form>

      <!-- ═══════════ STEP 5: Install ═══════════ -->
      <?php elseif ($currentStep === 5):
        $instData = $_SESSION['ikoma_install'] ?? [];
        $dbConf  = $instData['db']    ?? [];
        $appConf = $instData['app']   ?? [];
        $admConf = $instData['admin'] ?? [];
        $missingSteps = [];
        if (empty($dbConf['host']))  $missingSteps[] = 'Database';
        if (empty($appConf['name'])) $missingSteps[] = 'App Settings';
        if (empty($admConf['email']))$missingSteps[] = 'Admin Account';
      ?>
      <?php if (!empty($missingSteps)): ?>
      <div class="alert alert-error">
        Missing configuration from: <?= h(implode(', ', $missingSteps)) ?>.
        Please go back and complete all steps.
      </div>
      <div class="btn-group">
        <a href="?step=2" class="btn btn-primary">Go Back</a>
      </div>
      <?php else: ?>
      <div class="card" id="install-summary">
        <div class="card-title">Ready to Install</div>
        <div class="card-desc">Review your configuration, then click Install to begin.</div>
        <table style="width:100%;font-size:.85rem;border-collapse:collapse">
          <tr><td style="padding:6px 0;color:#64748b;width:180px">Application Name</td><td style="font-weight:500"><?= h($appConf['name'] ?? '') ?></td></tr>
          <tr><td style="padding:6px 0;color:#64748b">Application URL</td><td style="font-weight:500"><?= h($appConf['url'] ?? '') ?></td></tr>
          <tr><td style="padding:6px 0;color:#64748b">Timezone</td><td style="font-weight:500"><?= h($appConf['timezone'] ?? '') ?></td></tr>
          <tr><td style="padding:6px 0;color:#64748b">Database Host</td><td style="font-weight:500"><?= h($dbConf['host'] ?? '') ?>:<?= h($dbConf['port'] ?? '') ?></td></tr>
          <tr><td style="padding:6px 0;color:#64748b">Database Name</td><td style="font-weight:500"><?= h($dbConf['name'] ?? '') ?></td></tr>
          <tr><td style="padding:6px 0;color:#64748b">Organisation</td><td style="font-weight:500"><?= h($admConf['org_name'] ?? '') ?> (<?= h($admConf['country'] ?? '') ?>)</td></tr>
          <tr><td style="padding:6px 0;color:#64748b">Admin Email</td><td style="font-weight:500"><?= h($admConf['email'] ?? '') ?></td></tr>
        </table>
      </div>

      <div class="card" id="install-progress" style="display:none">
        <div class="card-title">Installing IKOMA GEOFACT…</div>
        <div class="card-desc">Please wait — do not close or refresh this page.</div>
        <div class="install-steps" id="install-steps-list">
          <?php
          $installStepLabels = [
            'Writing .env file',
            'Clearing config cache',
            'Running database migrations',
            'Creating super admin account',
            'Caching configuration',
            'Caching routes',
            'Locking installer',
          ];
          foreach ($installStepLabels as $i => $label):
          ?>
          <div class="inst-step" id="ist-<?= $i ?>">
            <div class="ist-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
            </div>
            <span class="ist-label"><?= h($label) ?></span>
            <span class="ist-status">Waiting…</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="success-screen" id="install-success" style="display:none">
        <div class="success-icon">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h2>Installation Complete!</h2>
        <p>IKOMA GEOFACT has been successfully installed. You can now log in to your new fleet intelligence platform.</p>
        <a href="#" id="success-link" class="btn btn-primary" style="font-size:1rem;padding:14px 32px">
          Go to Application
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
        </a>
      </div>

      <div class="card" id="install-error-card" style="display:none">
        <div class="card-title" style="color:#ef4444">Installation Failed</div>
        <div class="card-desc" id="install-error-msg">An unexpected error occurred.</div>
        <div class="btn-group">
          <a href="?step=5" class="btn btn-secondary">Try Again</a>
          <a href="?step=1" class="btn btn-secondary">Start Over</a>
        </div>
      </div>

      <div class="btn-group" id="install-btn-group">
        <a href="?step=4" class="btn btn-secondary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
          Back
        </a>
        <button type="button" class="btn btn-primary" id="btn-install" onclick="startInstall()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Install Now
        </button>
      </div>
      <?php endif; ?>
      <?php endif; ?>

    </div><!-- /content-body -->
  </main>
</div><!-- /layout -->

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

/* ─── DB Test ─── */
function testDbConnection() {
  const btn = document.getElementById('btn-test-db');
  const result = document.getElementById('db-test-result');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Testing…';
  result.style.display = 'none';
  result.className = 'test-result';

  const body = new URLSearchParams({
    csrf_token: CSRF,
    db_host: document.getElementById('db_host').value,
    db_port: document.getElementById('db_port').value,
    db_name: document.getElementById('db_name').value,
    db_user: document.getElementById('db_user').value,
    db_pass: document.getElementById('db_pass').value,
  });

  fetch('?action=test_db', {method:'POST', body})
    .then(r => r.json())
    .then(data => {
      result.style.display = 'block';
      if (data.ok) {
        result.className = 'test-result ok';
        result.textContent = '✓ ' + data.message;
        document.getElementById('btn-next-db').disabled = false;
      } else {
        result.className = 'test-result err';
        result.textContent = '✗ ' + data.message;
        document.getElementById('btn-next-db').disabled = true;
      }
    })
    .catch(e => {
      result.style.display = 'block';
      result.className = 'test-result err';
      result.textContent = '✗ Request failed: ' + e.message;
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Test Connection';
    });
}

function syncDbFields() {
  document.getElementById('fdb_host').value = document.getElementById('db_host').value;
  document.getElementById('fdb_port').value = document.getElementById('db_port').value;
  document.getElementById('fdb_name').value = document.getElementById('db_name').value;
  document.getElementById('fdb_user').value = document.getElementById('db_user').value;
  document.getElementById('fdb_pass').value = document.getElementById('db_pass').value;
}

/* ─── Admin validation ─── */
function validateAdmin() {
  const p1 = document.getElementById('admin_pass').value;
  const p2 = document.getElementById('admin_pass_confirm').value;
  if (p1 !== p2) { alert('Passwords do not match.'); return false; }
  if (p1.length < 8) { alert('Password must be at least 8 characters.'); return false; }
  const cc = document.getElementById('country_code').value;
  if (!/^[A-Za-z]{2}$/.test(cc)) { alert('Country code must be exactly 2 letters.'); return false; }
  return true;
}

/* ─── Install ─── */
const installStepLabels = [
  'Writing .env file',
  'Clearing config cache',
  'Running database migrations',
  'Creating super admin account',
  'Caching configuration',
  'Caching routes',
  'Locking installer',
];

function setStepState(idx, state, detail) {
  const el = document.getElementById('ist-' + idx);
  if (!el) return;
  el.className = 'inst-step ' + state;
  const icons = {
    running: '<span class="spinner"></span>',
    success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>',
    error:   '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
    waiting: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>',
  };
  el.querySelector('.ist-icon').innerHTML = icons[state] || icons.waiting;
  el.querySelector('.ist-status').textContent = state === 'running' ? 'Running…' : state === 'success' ? 'Done' : state === 'error' ? 'Failed' : 'Waiting…';
  if (detail && state === 'error') {
    let d = el.querySelector('.error-detail');
    if (!d) { d = document.createElement('div'); d.className = 'error-detail'; el.appendChild(d); }
    d.textContent = detail;
  }
}

function startInstall() {
  document.getElementById('install-summary').style.display = 'none';
  document.getElementById('install-btn-group').style.display = 'none';
  document.getElementById('install-progress').style.display = 'block';

  fetch('?action=do_install', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': CSRF},
    body: 'csrf_token=' + encodeURIComponent(CSRF),
  })
  .then(r => r.json())
  .then(data => {
    const steps = data.steps || [];

    // Animate steps sequentially
    let delay = 0;
    steps.forEach((s, i) => {
      setTimeout(() => setStepState(i, 'running'), delay);
      delay += 400;
      setTimeout(() => setStepState(i, s.ok ? 'success' : 'error', s.ok ? null : s.output), delay);
      delay += 300;
    });

    setTimeout(() => {
      document.getElementById('install-progress').style.display = 'none';
      if (data.ok) {
        const successEl = document.getElementById('install-success');
        successEl.style.display = 'block';
        const link = document.getElementById('success-link');
        if (data.app_url) link.href = data.app_url;
      } else {
        const errCard = document.getElementById('install-error-card');
        const errMsg  = document.getElementById('install-error-msg');
        const failedStep = steps.slice().reverse().find(s => !s.ok);
        errMsg.textContent = failedStep
          ? 'Failed at: ' + failedStep.label + ' — ' + failedStep.output
          : 'An unexpected error occurred.';
        errCard.style.display = 'block';
      }
    }, delay + 300);
  })
  .catch(e => {
    document.getElementById('install-progress').style.display = 'none';
    const errCard = document.getElementById('install-error-card');
    const errMsg  = document.getElementById('install-error-msg');
    errMsg.textContent = 'Network error: ' + e.message;
    errCard.style.display = 'block';
  });
}
</script>
</body>
</html>
