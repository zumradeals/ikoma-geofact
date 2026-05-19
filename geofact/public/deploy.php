<?php
/**
 * IKOMA GEOFACT — Webhook de déploiement automatique GitHub
 *
 * Configurez un webhook dans votre dépôt GitHub :
 *   Settings → Webhooks → Add webhook
 *   Payload URL : https://base.ikomagroup.net/deploy.php
 *   Content type : application/json
 *   Secret : la valeur de DEPLOY_SECRET ci-dessous
 *   Events : Just the push event
 *
 * SÉCURITÉ : changez DEPLOY_SECRET avant de mettre en ligne.
 */

// ── Configuration ────────────────────────────────────────────────────────────

// Lit DEPLOY_WEBHOOK_SECRET depuis le .env Laravel (hors bootstrap Laravel)
function readDotEnvSecret(): string
{
    $envFile = dirname(__DIR__) . '/.env';
    if (! is_readable($envFile)) return '';
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_starts_with($line, 'DEPLOY_WEBHOOK_SECRET=')) {
            return trim(substr($line, strlen('DEPLOY_WEBHOOK_SECRET=')), " \t\"'");
        }
    }
    return '';
}

define('DEPLOY_SECRET',  getenv('DEPLOY_WEBHOOK_SECRET') ?: readDotEnvSecret() ?: 'CHANGEZ_CE_SECRET_ICI');
define('DEPLOY_BRANCH',  'master');
define('REPO_RAW_BASE',  'https://raw.githubusercontent.com/zumradeals/ikoma-geofact');
define('REPO_SUBFOLDER', 'geofact');             // sous-dossier dans le dépôt
define('APP_ROOT',       dirname(__DIR__));      // dossier Laravel (un niveau au-dessus de /public)
define('LOG_FILE',       APP_ROOT . '/storage/logs/deploy.log');

// ── Fonctions utilitaires ─────────────────────────────────────────────────────

function deployLog(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function deployRespond(int $code, string $msg): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    deployLog("HTTP {$code} — {$msg}");
    echo json_encode(['status' => $code, 'message' => $msg]);
    exit;
}

function downloadFile(string $branch, string $repoPath): bool
{
    // repoPath est relatif à la racine du dépôt, ex: "geofact/app/Foo.php"
    $rawUrl   = REPO_RAW_BASE . "/{$branch}/" . ltrim($repoPath, '/');
    // Chemin local : on retire le préfixe REPO_SUBFOLDER
    $localRel = substr($repoPath, strlen(REPO_SUBFOLDER . '/'));
    $localAbs = APP_ROOT . '/' . ltrim($localRel, '/');

    // Crée les dossiers intermédiaires si nécessaire
    $dir = dirname($localAbs);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 15,
            'ignore_errors' => true,
            'user_agent'    => 'IKOMA-Deploy/1.0',
        ],
    ]);

    $content = @file_get_contents($rawUrl, false, $ctx);

    // Vérifie que GitHub a bien retourné le fichier (pas une 404)
    if ($content === false || str_starts_with($content, '404: Not Found')) {
        deployLog("  FAIL download: {$rawUrl}");
        return false;
    }

    file_put_contents($localAbs, $content, LOCK_EX);
    deployLog("  OK  {$localRel}");
    return true;
}

function clearLaravelCache(): void
{
    $php = PHP_BINARY ?: 'php';
    $artisan = APP_ROOT . '/artisan';
    exec("{$php} {$artisan} optimize:clear 2>&1", $out, $code);
    deployLog('artisan optimize:clear → exit ' . $code . ' — ' . implode(' | ', $out));
}

// ── Vérification de la requête ────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    deployRespond(405, 'Method Not Allowed');
}

$rawBody = file_get_contents('php://input');

// Signature HMAC-SHA256
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected  = 'sha256=' . hash_hmac('sha256', $rawBody, DEPLOY_SECRET);

if (! hash_equals($expected, $signature)) {
    deployRespond(403, 'Invalid signature');
}

$payload = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    deployRespond(400, 'Invalid JSON payload');
}

// Seul le push sur la branche configurée déclenche le déploiement
$ref = $payload['ref'] ?? '';
if ($ref !== 'refs/heads/' . DEPLOY_BRANCH) {
    deployRespond(200, "Ignored (branch: {$ref})");
}

// ── Collecte des fichiers modifiés ────────────────────────────────────────────

$branch  = DEPLOY_BRANCH;
$commits = $payload['commits'] ?? [];

$filesToDeploy = [];
foreach ($commits as $commit) {
    foreach (['added', 'modified'] as $key) {
        foreach ($commit[$key] ?? [] as $file) {
            // N'inclut que les fichiers du sous-dossier du projet
            if (str_starts_with($file, REPO_SUBFOLDER . '/')) {
                $filesToDeploy[$file] = true;
            }
        }
    }
}

if (empty($filesToDeploy)) {
    deployRespond(200, 'No relevant files changed');
}

// ── Déploiement ───────────────────────────────────────────────────────────────

$commitId = substr($payload['after'] ?? 'unknown', 0, 8);
deployLog("=== Deploy triggered — commit {$commitId} — " . count($filesToDeploy) . " file(s)");

$ok   = 0;
$fail = 0;

foreach (array_keys($filesToDeploy) as $file) {
    if (downloadFile($branch, $file)) {
        $ok++;
    } else {
        $fail++;
    }
}

clearLaravelCache();

deployLog("=== Done — {$ok} OK, {$fail} failed");
deployRespond(200, "Deployed {$ok} file(s)" . ($fail ? ", {$fail} failed" : ''));
