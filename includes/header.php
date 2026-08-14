<?php
/**
 * includes/header.php
 * À inclure après avoir démarré la session et chargé Auth.
 * Variable optionnelle $pageTitre définie avant l'include.
 */
$pageTitre = $pageTitre ?? APP_NAME;
$roleActuel = Auth::role();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitre) ?> — <?= e(APP_NAME) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <?php
        // Cache-buster : force le navigateur à recharger le CSS dès qu'on le modifie,
        // en ajoutant l'heure de dernière modification du fichier dans l'URL.
        $cssPath = __DIR__ . '/../public/assets/css/style.css';
        $cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
    ?>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= $cssVersion ?>">
</head>
<body>

<?php if (Auth::estConnecte()): ?>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= APP_URL ?>/../modules/dashboard/index.php">
            <i class="bi bi-cash-coin"></i> <?= e(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/../modules/dashboard/index.php">
                        <i class="bi bi-speedometer2"></i> Tableau de bord
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/../modules/campagnes/liste.php">
                        <i class="bi bi-megaphone"></i> Campagnes
                    </a>
                </li>
                <?php if ($roleActuel === 'contributeur'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/../modules/contributions/mes_contributions.php">
                        <i class="bi bi-receipt"></i> Mes contributions
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($roleActuel === 'porteur'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/../modules/contributions/liste.php">
                        <i class="bi bi-cash-stack"></i> Contributions reçues
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($roleActuel === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/../modules/paiements/liste.php">
                        <i class="bi bi-wallet2"></i> Transactions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/../modules/admin/utilisateurs.php">
                        <i class="bi bi-people"></i> Utilisateurs
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text text-white me-3">
                <span class="badge bg-warning text-dark"><?= e(ucfirst($roleActuel)) ?></span>
                <?= e($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']) ?>
            </span>
            <a href="<?= APP_URL ?>/../modules/auth/logout.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right"></i> Déconnexion
            </a>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="container-fluid py-4">
