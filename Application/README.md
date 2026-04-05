# IA-NAHA — Documentation Technique

---

## 1. Prérequis

### Serveur local

| Outil | macOS | Windows |
|---|---|---|
| Serveur Apache + PHP | MAMP (port **8888**) | XAMPP ou WAMP (port **80**) |
| MySQL | MAMP (port **8889**) | XAMPP/WAMP (port **3306**) |
| PHP | ≥ 7.2 | ≥ 7.2 |
| Python | 3.8+ (`python3`) | 3.8+ (`python`) |

### Dépendances Python

```bash
pip install flask joblib numpy pandas scikit-learn
# optionnel mais recommandé sur Windows :
pip install waitress
```

### Fichier `.env`

Créer `Application/.env` (ne jamais le commiter) :

```
GEMINI_API_KEY=AIza...votre_clé...
```

> Clé obtenue sur : https://aistudio.google.com/app/apikey

### Base de données

- Créer une BDD MySQL nommée `ia-naha`
- Importer le fichier SQL du projet
- Vérifier dans `Application/api/config.php` :
  - `DB_PORT` → `8889` sur MAMP, `3306` sur XAMPP/WAMP

### Démarrer le serveur ML

```bash
# macOS
python3 Application/api/ml_server.py

# Windows
python Application/api/ml_server.py
```

Le serveur Flask écoute sur `http://127.0.0.1:5050`. Il doit tourner en parallèle d'Apache pour que la prédiction de sommeil fonctionne.

---

## 2. Bugs connus et solutions

### Apache bloque le header `Authorization`

**Symptôme :** toutes les requêtes authentifiées retournent `401 Non authentifié` même après connexion.

**Cause :** Apache (MAMP et XAMPP) filtre le header `Authorization: Bearer` avant qu'il n'atteigne PHP.

**Solution :** le fichier `Application/api/.htaccess` contient :
```apache
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
```
Et le frontend envoie toujours un second header `X-Auth-Token` (non bloqué par Apache) que PHP lit en fallback.

> Si `.htaccess` ne fonctionne pas, vérifier que `AllowOverride All` est activé dans la config Apache du projet.

---

### Clé Gemini invalide sur Windows

**Symptôme :** la génération de plan échoue avec une erreur Gemini, alors que la clé est correcte dans `.env`.

**Cause :** Notepad (Windows) sauvegarde les fichiers en `CRLF`. `parse_ini_file()` lit alors `AIza...\r` au lieu de `AIza...` — la clé est corrompue par un `\r` invisible.

**Solution appliquée dans `gemini.php` :**
```php
define('GEMINI_API_KEY', trim($_env['GEMINI_API_KEY'] ?? ''));
// trim() retire le \r de fin si le .env a été sauvegardé en CRLF
```

> Toujours éditer le `.env` avec VS Code ou Notepad++ en encodage **UTF-8 sans BOM**.

---

### URL API ne fonctionne que sur macOS

**Symptôme :** sur Windows avec XAMPP, toutes les requêtes échouent (ERR_CONNECTION_REFUSED).

**Cause :** l'URL était hardcodée à `http://localhost:8888/...` — le port MAMP macOS.

**Solution appliquée dans tous les fichiers HTML :**
```javascript
// Avant (cassé sur Windows)
const API = 'http://localhost:8888/SD4/IA-NAHA/Application/api';

// Après (cross-platform)
const API = window.location.origin + '/SD4/IA-NAHA/Application/api';
// → http://localhost:8888/... sur MAMP
// → http://localhost/...    sur XAMPP
```

---

### Gemini renvoie du texte au lieu du JSON

**Symptôme :** `"The string did not match the expected pattern"` dans Safari, `JSON.parse` échoue.

**Cause :** Gemini 2.5 Flash en mode "thinking" renvoie plusieurs parts dans sa réponse. Le premier part contient sa réflexion interne (texte brut), pas le JSON.

**Solution :** `thinkingBudget: 0` désactive ce comportement + le code parcourt les parts à l'envers pour prendre le bon.

---

### Le serveur ML ne démarre pas

**Symptôme :** `ModuleNotFoundError` ou `FileNotFoundError` au lancement.

**Solutions :**
- Installer les dépendances : `pip install flask joblib numpy pandas scikit-learn`
- Vérifier que les fichiers `.joblib` existent dans `/IA-NAHA/modeles/`
- Sur Windows, lancer depuis le répertoire du projet en tant qu'administrateur si besoin

---

## 3. Code important commenté

### `config.php` — Connexion BDD (`getPDO`)

```php
function getPDO(): PDO {
    static $pdo = null;
    // Instance unique (singleton) — évite d'ouvrir plusieurs connexions MySQL
    if ($pdo) return $pdo;

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // lance des exceptions sur erreur SQL
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // retourne des tableaux associatifs
            PDO::ATTR_EMULATE_PREPARES   => false,                    // requêtes préparées natives (sécurité)
        ]);
    } catch (PDOException $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['error' => 'Connexion BDD impossible : ' . $e->getMessage()]);
        exit;
    }
    return $pdo;
}
```

---

### `config.php` — Authentification (`requireAuth`)

```php
function requireAuth(): int {
    // Apache/MAMP peut bloquer le header Authorization standard.
    // On essaie 4 sources dans l'ordre, de la plus standard à la plus permissive.

    $auth = $_SERVER['HTTP_AUTHORIZATION']           // source standard
         ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']  // après une RewriteRule Apache
         ?? '';

    if (!$auth && function_exists('getallheaders')) {
        $h    = array_change_key_case(getallheaders(), CASE_LOWER);
        $auth = $h['authorization'] ?? '';
    }

    $token = trim(str_replace('Bearer', '', $auth)); // extrait le token du "Bearer <token>"

    // Fallback : header X-Auth-Token envoyé par le frontend en parallèle.
    // Ce header custom n'est jamais bloqué par Apache.
    if (!$token) {
        $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
        if (!$token && function_exists('getallheaders')) {
            $h     = array_change_key_case(getallheaders(), CASE_LOWER);
            $token = $h['x-auth-token'] ?? '';
        }
        $token = trim($token);
    }

    if (!$token) {
        ob_clean(); http_response_code(401);
        echo json_encode(['error' => 'Non authentifié']); exit;
    }

    // Vérifie que le token existe en base → retourne l'user_id associé
    $stmt = getPDO()->prepare('SELECT user_id FROM user_sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        ob_clean(); http_response_code(401);
        echo json_encode(['error' => 'Session invalide ou expirée']); exit;
    }

    return (int)$row['user_id'];
}
```

---

### `login.php` — Connexion avec rate limiting

```php
// Bloque une IP après 10 échecs en 15 minutes (brute-force protection)
$stmt = $pdo->prepare('
    SELECT COUNT(*) FROM login_logs
    WHERE ip_address = ? AND success = 0
    AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
');
$stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '']);
if ((int)$stmt->fetchColumn() >= 10) {
    jsonOut(['error' => 'Trop de tentatives. Réessayez dans 15 minutes.'], 429);
}

// password_verify() compare le mot de passe avec le hash bcrypt en base.
// Le message d'erreur est volontairement identique qu'il s'agisse d'un
// mauvais email ou d'un mauvais mot de passe (évite l'énumération de comptes).
if (!$user || !password_verify($password, $user['password'])) {
    jsonOut(['error' => 'Email ou mot de passe incorrect.'], 401);
}

// Token de session : 32 octets aléatoires = 64 caractères hex, non-devinable
$token = bin2hex(random_bytes(32));
```

---

### `gemini.php` — Proxy Gemini

```php
// La clé API n'est jamais exposée côté client.
// PHP lit le .env côté serveur et injecte la clé dans la requête.
// trim() retire un éventuel \r si le .env a été sauvegardé en CRLF (Windows).
$_env = parse_ini_file(__DIR__ . '/../.env') ?: [];
define('GEMINI_API_KEY', trim($_env['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: ''));

// thinkingBudget: 0 désactive le mode "thinking" de Gemini 2.5 Flash.
// Sans ça, la réponse contient plusieurs "parts" dont le premier est la
// réflexion interne du modèle (thought:true) — pas du JSON parseable.
'thinkingConfig' => ['thinkingBudget' => 0]

// Fallback : si malgré tout le thinking est actif, on parcourt les parts
// à l'envers pour ignorer les thoughts et prendre le vrai contenu.
foreach (array_reverse($parts) as $part) {
    if (!($part['thought'] ?? false) && isset($part['text'])) {
        $text = $part['text'];
        break;
    }
}

// trim() sur la clé + mb_convert_encoding évitent que json_encode()
// retourne false sur des caractères UTF-8 invalides dans la réponse Gemini.
$text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
```

---

### `save_plan.php` — Encodages pour le modèle ML

```php
// Ces fonctions convertissent les valeurs texte du formulaire en entiers.
// Les mappings DOIVENT rester synchronisés avec ml_server.py.

function encodeGender(string $v): int {
    return in_array(strtolower($v), ['homme', 'm', 'male']) ? 1 : 0;
}

function encodeIntensity(string $v): int {
    $map = [
        'high'=>0, 'actif'=>0, 'tres_actif'=>0,  // haute intensité → 0
        'low'=>1, 'sedentaire'=>1, 'leger'=>1,    // basse intensité → 1
        'medium'=>2, 'modere'=>2,                 // intensité modérée → 2
    ];
    return $map[strtolower($v)] ?? 2; // valeur par défaut : modéré
}

// BMI calculé côté serveur à partir du poids et de la taille.
// On ne fait pas confiance à une valeur éventuellement envoyée par le client.
$bmi = ($poids > 0 && $taille > 0)
    ? round($poids / (($taille / 100) ** 2), 2)
    : null;
```

---

### `ml_server.py` — Prédiction du sommeil

```python
# os.path.join est cross-platform : fonctionne sur macOS et Windows.
# Ne jamais hardcoder des slashes ou backslashes.
BASE_DIR  = os.path.dirname(os.path.abspath(__file__))  # dossier du script
MODEL_DIR = os.path.join(BASE_DIR, '..', '..', 'modeles')

# Le scaler (StandardScaler) doit être celui sauvegardé à l'entraînement.
# Appliquer un scaler différent fausserait complètement la prédiction.
x_scaled = scaler.transform(features)
pred     = float(model.predict(x_scaled)[0])

# Clamping entre 4h et 12h : le modèle peut extrapoler hors des bornes
# raisonnables pour des profils extrêmes — on limite la valeur retournée.
pred = round(max(4.0, min(12.0, pred)), 1)
```

---

### `generate.html` — Double header d'authentification

```javascript
// Authorization: Bearer → header standard, peut être bloqué par Apache.
// X-Auth-Token → header custom, jamais bloqué, lu en fallback côté PHP.
// Les deux sont envoyés systématiquement sur toute requête protégée.
const authHeaders = {
    'Content-Type':  'application/json',
    'Authorization': `Bearer ${localStorage.getItem('naha_token')}`,
    'X-Auth-Token':   localStorage.getItem('naha_token'),
};
```
