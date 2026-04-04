<?php
// ─────────────────────────────────────────────
// CIQUAL API — IA-NAHA
// Retourne les aliments prioritaires depuis la BD
// ─────────────────────────────────────────────
require_once __DIR__ . '/config.php';

$priority = [
    'viandes cuites', 'viandes crues',
    'poissons cuits', 'poissons crus',
    'mollusques et crustacés cuits', 'mollusques et crustacés crus',
    'produits à base de poissons et produits de la mer',
    'charcuteries et alternatives végétales',
    'oeufs',
    'légumes', 'légumineuses', 'fruits',
    'pâtes, riz et céréales', 'pains et assimilés',
    'fromages et alternatives végétales',
    'produits laitiers frais et alternatives végétales',
    'laits', 'crèmes et spécialités à base de crème',
    'fruits à coque et graines oléagineuses',
    'ingrédients pour végétariens',
    'huiles et graisses végétales',
    'pommes de terre et autres tubercules',
    'céréales de petit-déjeuner',
    'sucres, miels et assimilés',
    'aides culinaires', 'épices', 'herbes', 'condiments',
];

$placeholders = implode(',', array_fill(0, count($priority), '?'));

$pdo  = getPDO();
$stmt = $pdo->prepare("
    SELECT
        sous_groupe        AS g,
        nom                AS n,
        calories_kcal_100g AS c,
        proteines_g_100g   AS p,
        glucides_g_100g    AS gl,
        lipides_g_100g     AS l,
        fibres_g_100g      AS f
    FROM ciqual_nutrition
    WHERE sous_groupe IN ($placeholders)
      AND calories_kcal_100g IS NOT NULL
      AND nom IS NOT NULL
    ORDER BY sous_groupe, nom
");
$stmt->execute($priority);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$row) {
    $row['c']  = (float)$row['c'];
    $row['p']  = (float)$row['p'];
    $row['gl'] = (float)$row['gl'];
    $row['l']  = (float)$row['l'];
    $row['f']  = (float)$row['f'];
}

ob_clean();
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
exit;
