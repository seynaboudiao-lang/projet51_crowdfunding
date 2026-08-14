<?php
/**
 * includes/functions.php
 * Fonctions utilitaires transverses.
 */

/**
 * Échappe une chaîne pour affichage HTML (protection XSS).
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Génère (ou retourne) un jeton CSRF stocké en session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie le jeton CSRF envoyé par un formulaire.
 */
function csrf_verify(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Formate un montant en Francs CFA.
 */
function formatMontant(float $montant): string
{
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}

/**
 * Calcule le pourcentage d'avancement d'une campagne (borné à 100 pour l'affichage).
 */
function tauxAvancement(float $collecte, float $objectif): float
{
    if ($objectif <= 0) return 0;
    return min(100, round(($collecte / $objectif) * 100, 1));
}

/**
 * Journalise une action sensible dans journal_audit (piste d'audit).
 */
function journaliser(PDO $pdo, ?int $utilisateurId, string $action, string $table, ?int $enregistrementId = null, ?string $details = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO journal_audit (utilisateur_id, action, table_concernee, enregistrement_id, details, adresse_ip)
         VALUES (:uid, :action, :table_c, :rec_id, :details, :ip)'
    );
    $stmt->execute([
        ':uid'     => $utilisateurId,
        ':action'  => $action,
        ':table_c' => $table,
        ':rec_id'  => $enregistrementId,
        ':details' => $details,
        ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/**
 * Redirige vers une URL et arrête l'exécution du script.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Génère un slug URL-friendly à partir d'un titre.
 */
function slugify(string $texte): string
{
    $texte = iconv('UTF-8', 'ASCII//TRANSLIT', $texte);
    $texte = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $texte));
    return trim($texte, '-') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

/**
 * Calcule les paramètres de pagination.
 * Retourne [page, offset, limite].
 */
function paginationParams(int $totalItems, int $itemsParPage = ITEMS_PAR_PAGE): array
{
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $totalPages = max(1, (int) ceil($totalItems / $itemsParPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $itemsParPage;
    return [$page, $offset, $itemsParPage, $totalPages];
}

/**
 * Génère une référence de transaction mobile money unique et lisible.
 * Format : OPERATEUR-AAAAMMJJHHMMSS-XXXX
 */
function genererReferenceTransaction(string $operateur): string
{
    return strtoupper($operateur) . '-' . date('YmdHis') . '-' . random_int(1000, 9999);
}

/**
 * Valide un numéro de téléphone sénégalais simplifié (9 chiffres commençant par 7).
 * Accepte les formats avec espaces ("77 123 45 67") qui sont nettoyés avant contrôle.
 */
function telephoneSenegalaisValide(string $telephone): bool
{
    $nettoye = preg_replace('/[^0-9]/', '', $telephone);
    return (bool) preg_match('/^7[0-8][0-9]{7}$/', $nettoye);
}

/**
 * Retourne le libellé lisible d'un opérateur de paiement.
 */
function libelleOperateur(string $operateur): string
{
    $libelles = [
        'wave'         => 'Wave',
        'orange_money' => 'Orange Money',
        'free_money'   => 'Free Money',
        'carte'        => 'Carte bancaire',
    ];
    return $libelles[$operateur] ?? ucfirst($operateur);
}
