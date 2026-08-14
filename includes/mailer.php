<?php
/**
 * includes/mailer.php
 * Envoi d'email automatique — exigence du cahier des charges.
 *
 * Utilise la fonction native mail() de PHP plutôt que PHPMailer afin de
 * rester "exécutable sur tout poste XAMPP standard sans installation
 * complexe" (pas de composer, pas de dépendance à télécharger).
 *
 * IMPORTANT (voir README.md, section "Configurer l'envoi d'email") :
 * mail() nécessite un agent d'envoi configuré (sendmail.ini pointant vers
 * un relai SMTP sous Windows/XAMPP, ou Mercury Mail). Sans cette
 * configuration, mail() renverra false : l'application ne plante pas pour
 * autant, l'échec est simplement journalisé dans journal_audit.
 */

/**
 * Envoie un email HTML simple. Retourne true si accepté pour livraison
 * par le serveur de mail local (pas une garantie de réception finale).
 */
function envoyerEmail(string $destinataire, string $sujet, string $corpsHtml): bool
{
    if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $entetes  = "MIME-Version: 1.0\r\n";
    $entetes .= "Content-Type: text/html; charset=UTF-8\r\n";
    $entetes .= 'From: ' . APP_NAME . ' <no-reply@crowdfunding.sn>' . "\r\n";

    // Sujet encodé pour supporter les accents (RFC 2047)
    $sujetEncode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';

    // @ : on ne veut pas d'avertissement PHP si le serveur mail local
    // n'est pas configuré ; l'échec est géré par la valeur de retour.
    return @mail($destinataire, $sujetEncode, $corpsHtml, $entetes);
}

/**
 * Envoie l'email de confirmation au contributeur après validation d'un
 * paiement mobile money, et journalise le résultat (succès ou échec).
 */
function envoyerEmailConfirmationContribution(PDO $pdo, array $contribution, array $campagne, array $utilisateur, array $paiement): bool
{
    $sujet = 'Confirmation de votre contribution — ' . APP_NAME;

    $corps = '<div style="font-family:Segoe UI,Arial,sans-serif; color:#212529; max-width:520px;">'
        . '<h2 style="color:#B3186B; margin-bottom:4px;">Merci pour votre soutien !</h2>'
        . '<p>Bonjour ' . e($utilisateur['prenom']) . ',</p>'
        . '<p>Votre contribution de <strong>' . formatMontant((float) $contribution['montant']) . '</strong> '
        . 'à la campagne « ' . e($campagne['titre']) . ' » a été <strong>validée avec succès</strong>.</p>'
        . '<table style="border-collapse:collapse; margin:16px 0; font-size:14px;">'
        . '<tr><td style="padding:4px 12px 4px 0; color:#6c757d;">Référence</td><td style="padding:4px 0;"><strong>' . e($paiement['reference_transaction']) . '</strong></td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#6c757d;">Opérateur</td><td style="padding:4px 0;">' . e(libelleOperateur($paiement['operateur'])) . '</td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#6c757d;">Date</td><td style="padding:4px 0;">' . e(date('d/m/Y H:i')) . '</td></tr>'
        . '</table>'
        . '<p>Vous pouvez télécharger votre reçu officiel au format PDF depuis votre espace « Mes contributions ».</p>'
        . '<p style="color:#adb5bd; font-size:12px; margin-top:24px;">' . e(APP_NAME) . ' — Master CCA, ESP Dakar.</p>'
        . '</div>';

    $envoye = envoyerEmail($utilisateur['email'], $sujet, $corps);

    journaliser(
        $pdo,
        (int) $utilisateur['id'],
        $envoye ? 'EMAIL_CONFIRMATION_ENVOYE' : 'EMAIL_CONFIRMATION_ECHEC',
        'contributions',
        (int) $contribution['id'],
        'Référence : ' . $paiement['reference_transaction']
    );

    return $envoye;
}
