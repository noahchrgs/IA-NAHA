<?php
// ─────────────────────────────────────────────
// LOGIN — IA-NAHA
// ─────────────────────────────────────────────
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['error' => 'Méthode non autorisée'], 405);
}

$body = getBody();

$email    = trim($body['email']    ?? '');
$password =       $body['password'] ?? '';

// ── Validation basique ──
if (!$email || !$password) {
    jsonOut(['error' => 'Email et mot de passe requis.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(['error' => 'Email ou mot de passe incorrect.'], 401);
}

$pdo = getPDO();

// ── Rate limiting : max 10 tentatives / 15 min par IP ──
try {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM login_logs
        WHERE ip_address = ? AND success = 0
        AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ');
    $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '']);
    if ((int)$stmt->fetchColumn() >= 10) {
        jsonOut(['error' => 'Trop de tentatives. Réessayez dans 15 minutes.'], 429);
    }
} catch (\Throwable $e) { /* non bloquant si table absente */ }

// ── Cherche l'utilisateur ──
$stmt = $pdo->prepare('SELECT id, prenom, nom, password FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    // Réponse volontairement vague pour la sécurité
    jsonOut(['error' => 'Email ou mot de passe incorrect.'], 401);
}

$userId = (int) $user['id'];

// ── Crée une session ──
$token     = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

try {
    $pdo->prepare('
        INSERT INTO user_sessions (id, user_id, payload, last_activity, ip_address)
        VALUES (?, ?, ?, UNIX_TIMESTAMP(), ?)
    ')->execute([$token, $userId, '', $_SERVER['REMOTE_ADDR'] ?? '']);
} catch (\Throwable $e) { /* non bloquant si table absente */ }

// ── Log ──
try {
    $pdo->prepare('INSERT INTO login_logs (user_id, email, ip_address, success, created_at) VALUES (?, ?, ?, 1, NOW())')
        ->execute([$userId, $email, $_SERVER['REMOTE_ADDR'] ?? '']);
} catch (\Throwable $e) { /* non bloquant */ }

jsonOut([
    'success'  => true,
    'user_id'  => $userId,
    'prenom'   => $user['prenom'],
    'nom'      => $user['nom'],
    'token'    => $token,
    'message'  => 'Connexion réussie.',
]);