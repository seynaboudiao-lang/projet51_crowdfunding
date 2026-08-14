<?php
/**
 * modules/paiements/traiter.php
 * -----------------------------------------------------------------------
 * Étape 2 du flux de contribution : confirmation du paiement mobile money.
 *
 * Wave, Orange Money et Free Money ne fournissent pas d'environnement de
 * test (sandbox) accessible publiquement aux étudiants. Cette page
 * reproduit donc, de façon pédagogique et clairement documentée, le
 * comportement attendu du webhook de confirmation que l'opérateur
 * appellerait normalement côté serveur après que le client ait validé
 * l'opération sur son téléphone (code PIN Wave / USSD Orange Money...).
 *
 * En conditions réelles, on remplacerait le bouton "Confirmer" par un
 * appel à l'API de l'opérateur (ex. Wave Checkout API) puis on
 * traiterait la confirmation via un endpoint webhook dédié appelé par
 * l'opérateur — la logique métier ci-dessous (mise à jour des montants,
 * du palier, envoi d'email) resterait strictement identique.
 * -----------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mailer.php';

Auth::exigerConnexion();
$pdo = Database::getConnection();
$userId = Auth::utilisateurId();
$role = Auth::role();

$paiementId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT p.*, c.id AS contribution_id, c.contributeur_id, c.campagne_id, c.montant AS montant_contribution,
            c.palier_id, c.statut AS statut_contribution
     FROM paiements p
     JOIN contributions c ON c.id = p.contribution_id
     WHERE p.id = :id'
);
$stmt->execute([':id' => $paiementId]);
$paiement = $stmt->fetch();

if (!$paiement) {
    http_response_code(404);
    die('Transaction introuvable.');
}
if ($role !== 'admin' && (int) $paiement['contributeur_id'] !== $userId) {
    http_response_code(403);
    die('Vous n\'êtes pas autorisé à traiter cette transaction.');
}

$stmt = $pdo->prepare('SELECT * FROM campagnes WHERE id = :id');
$stmt->execute([':id' => $paiement['campagne_id']]);
$campagne = $stmt->fetch();

$erreur = '';
$resultat = $_GET['resultat'] ?? '';

if ($paiement['statut'] === 'en_attente' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'confirmer') {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE paiements SET statut = 'reussi' WHERE id = :id")
                    ->execute([':id' => $paiementId]);

                $pdo->prepare("UPDATE contributions SET statut = 'validee' WHERE id = :id")
                    ->execute([':id' => $paiement['contribution_id']]);

                $pdo->prepare('UPDATE campagnes SET montant_collecte = montant_collecte + :montant WHERE id = :id')
                    ->execute([':montant' => $paiement['montant_contribution'], ':id' => $paiement['campagne_id']]);

                if ($paiement['palier_id']) {
                    $pdo->prepare('UPDATE paliers SET quantite_reservee = quantite_reservee + 1 WHERE id = :id')
                        ->execute([':id' => $paiement['palier_id']]);
                }

                // Une campagne active dont l'objectif est atteint ou dépassé passe automatiquement à "réussie"
                $verif = $pdo->prepare('SELECT montant_collecte, objectif_montant, statut FROM campagnes WHERE id = :id');
                $verif->execute([':id' => $paiement['campagne_id']]);
                $etatCampagne = $verif->fetch();
                if ($etatCampagne && $etatCampagne['statut'] === 'active' && (float) $etatCampagne['montant_collecte'] >= (float) $etatCampagne['objectif_montant']) {
                    $pdo->prepare("UPDATE campagnes SET statut = 'reussie' WHERE id = :id")->execute([':id' => $paiement['campagne_id']]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('Erreur confirmation paiement : ' . $e->getMessage());
                $erreur = 'Une erreur est survenue lors de la confirmation du paiement.';
            }

            if ($erreur === '') {
                journaliser($pdo, $userId, 'PAIEMENT_REUSSI', 'paiements', $paiementId, $paiement['reference_transaction']);

                // Envoi de l'email de confirmation automatique (exigence du cahier des charges)
                $utilisateur = $pdo->prepare('SELECT * FROM utilisateurs WHERE id = :id');
                $utilisateur->execute([':id' => $paiement['contributeur_id']]);
                $utilisateur = $utilisateur->fetch();

                $contributionActuelle = $pdo->prepare('SELECT * FROM contributions WHERE id = :id');
                $contributionActuelle->execute([':id' => $paiement['contribution_id']]);
                $contributionActuelle = $contributionActuelle->fetch();

                if ($utilisateur && $contributionActuelle && $campagne) {
                    envoyerEmailConfirmationContribution($pdo, $contributionActuelle, $campagne, $utilisateur, $paiement);
                }

                redirect('../campagnes/detail.php?id=' . $paiement['campagne_id'] . '&contribution=1');
            }
        } elseif ($action === 'echouer') {
            $pdo->prepare("UPDATE paiements SET statut = 'echoue' WHERE id = :id")->execute([':id' => $paiementId]);
            $pdo->prepare("UPDATE contributions SET statut = 'echouee' WHERE id = :id")->execute([':id' => $paiement['contribution_id']]);
            journaliser($pdo, $userId, 'PAIEMENT_ECHOUE', 'paiements', $paiementId, $paiement['reference_transaction']);

            redirect('../campagnes/detail.php?id=' . $paiement['campagne_id'] . '&paiement_echoue=1');
        }
    }
}

$pageTitre = 'Confirmation du paiement';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 text-center">

                <?php if ($paiement['statut'] !== 'en_attente'): ?>
                    <i class="bi bi-check-circle-fill text-<?= $paiement['statut'] === 'reussi' ? 'success' : 'danger' ?>" style="font-size:3rem;"></i>
                    <h4 class="fw-bold mt-3">
                        <?= $paiement['statut'] === 'reussi' ? 'Paiement déjà confirmé' : 'Ce paiement a échoué ou a été annulé' ?>
                    </h4>
                    <p class="text-muted">Référence : <?= e($paiement['reference_transaction']) ?></p>
                    <a href="../campagnes/detail.php?id=<?= $paiement['campagne_id'] ?>" class="btn btn-outline-secondary mt-2">
                        Retour à la campagne
                    </a>
                <?php else: ?>
                    <i class="bi bi-phone" style="font-size:3rem; color:#B3186B;"></i>
                    <h4 class="fw-bold mt-3">Confirmez votre paiement <?= e(libelleOperateur($paiement['operateur'])) ?></h4>

                    <?php if ($erreur): ?><div class="alert alert-danger mt-3"><?= e($erreur) ?></div><?php endif; ?>

                    <p class="text-muted">
                        Montant à régler : <strong class="text-success"><?= formatMontant($paiement['montant']) ?></strong><br>
                        Référence : <code><?= e($paiement['reference_transaction']) ?></code><br>
                        Campagne : <?= e($campagne['titre'] ?? '') ?>
                    </p>

                    <?php if ($paiement['operateur'] !== 'carte'): ?>
                        <div class="alert alert-light border small text-start">
                            <i class="bi bi-info-circle"></i>
                            En situation réelle, vous recevriez ici une notification <?= e(libelleOperateur($paiement['operateur'])) ?>
                            (code PIN ou requête USSD) sur votre téléphone pour valider la transaction. Cette page simule,
                            à des fins pédagogiques, le webhook de confirmation que l'opérateur enverrait normalement au serveur
                            (aucune sandbox publique Wave / Orange Money / Free Money n'étant accessible aux étudiants).
                        </div>
                    <?php endif; ?>

                    <form method="post" class="d-flex flex-column gap-2 mt-3">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= $paiementId ?>">
                        <button type="submit" name="action" value="confirmer" class="btn btn-success btn-lg">
                            <i class="bi bi-check-circle"></i> Confirmer le paiement (simuler succès)
                        </button>
                        <button type="submit" name="action" value="echouer" class="btn btn-outline-danger"
                                onclick="return confirm('Simuler un échec de paiement ?');">
                            <i class="bi bi-x-circle"></i> Simuler un échec
                        </button>
                        <a href="../campagnes/detail.php?id=<?= $paiement['campagne_id'] ?>" class="btn btn-link">Annuler</a>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
