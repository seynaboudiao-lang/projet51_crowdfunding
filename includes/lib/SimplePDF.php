<?php
/**
 * includes/lib/SimplePDF.php
 *
 * Générateur PDF minimaliste écrit sans aucune dépendance externe
 * (pas de composer, pas de TCPDF/DOMPDF à installer), pour respecter
 * l'exigence du cahier des charges : "exécutable sur tout poste XAMPP
 * standard sans installation complexe".
 *
 * Il construit directement la structure binaire d'un PDF valide
 * (catalogue, pages, police, flux de contenu, table xref) — suffisant
 * pour produire des documents texte simples : reçus, relevés, états.
 *
 * Utilisation :
 *   $pdf = new SimplePDF();
 *   $pdf->titre('Reçu de contribution')
 *       ->separateur()
 *       ->paire('Montant', '15 000 FCFA')
 *       ->telecharger('recu.pdf');
 */
class SimplePDF
{
    private const MARGE_GAUCHE = 50;
    private const LARGEUR_PAGE = 595; // A4 portrait en points
    private const HAUTEUR_PAGE = 842;

    private array $commandes = [];
    private float $y;

    public function __construct()
    {
        $this->y = self::HAUTEUR_PAGE - 60;
    }

    public function titre(string $texte, int $taille = 18): self
    {
        $this->ecrireLigne($texte, $taille, true);
        $this->y -= 6;
        return $this;
    }

    public function ligne(string $texte, int $taille = 11, bool $gras = false): self
    {
        $this->ecrireLigne($texte, $taille, $gras);
        return $this;
    }

    /**
     * Affiche une paire libellé / valeur alignée en deux colonnes
     * (utile pour les reçus et fiches de synthèse).
     */
    public function paire(string $label, string $valeur, int $taille = 11): self
    {
        $this->commandes[] = $this->commandeTexte($label, self::MARGE_GAUCHE, $this->y, $taille, false);
        $this->commandes[] = $this->commandeTexte($valeur, self::MARGE_GAUCHE + 190, $this->y, $taille, true);
        $this->y -= $taille + 8;
        return $this;
    }

    public function espace(int $pixels = 10): self
    {
        $this->y -= $pixels;
        return $this;
    }

    /**
     * Trace une ligne horizontale de séparation.
     */
    public function separateur(): self
    {
        $this->commandes[] = sprintf(
            '%d %.1F m %d %.1F l S',
            self::MARGE_GAUCHE,
            $this->y,
            self::LARGEUR_PAGE - self::MARGE_GAUCHE,
            $this->y
        );
        $this->y -= 16;
        return $this;
    }

    private function ecrireLigne(string $texte, int $taille, bool $gras): void
    {
        $this->commandes[] = $this->commandeTexte($texte, self::MARGE_GAUCHE, $this->y, $taille, $gras);
        $this->y -= $taille + 8;
    }

    private function commandeTexte(string $texte, float $x, float $y, int $taille, bool $gras): string
    {
        $police = $gras ? 'F2' : 'F1';
        $texteEchappe = $this->echapper($texte);
        return sprintf('BT /%s %d Tf 1 0 0 1 %.1F %.1F Tm (%s) Tj ET', $police, $taille, $x, $y, $texteEchappe);
    }

    /**
     * Convertit en Windows-1252 (proche de WinAnsiEncoding, seul jeu de
     * caractères garanti avec les polices standard Helvetica sans
     * fichier de police à embarquer) puis échappe les caractères
     * réservés de la syntaxe PDF.
     */
    private function echapper(string $texte): string
    {
        $converti = @iconv('UTF-8', 'CP1252//TRANSLIT', $texte);
        if ($converti === false) {
            $converti = $texte;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converti);
    }

    /**
     * Construit le fichier PDF final et le renvoie au navigateur
     * en téléchargement direct.
     */
    public function telecharger(string $nomFichier): void
    {
        $flux = implode("\n", $this->commandes);

        $objets = [];
        $objets[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objets[2] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objets[3] = "3 0 obj\n<< /Type /Page /Parent 2 0 R "
            . '/Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> '
            . '/MediaBox [0 0 ' . self::LARGEUR_PAGE . ' ' . self::HAUTEUR_PAGE . '] '
            . "/Contents 4 0 R >>\nendobj\n";
        $objets[4] = "4 0 obj\n<< /Length " . strlen($flux) . " >>\nstream\n$flux\nendstream\nendobj\n";
        $objets[5] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
        $objets[6] = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $decalages = [];
        foreach ($objets as $numero => $contenu) {
            $decalages[$numero] = strlen($pdf);
            $pdf .= $contenu;
        }
        $decalageXref = strlen($pdf);

        $pdf .= 'xref' . "\n" . '0 ' . (count($objets) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objets); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $decalages[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objets) + 1) . " /Root 1 0 R >>\nstartxref\n$decalageXref\n%%EOF";

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
            header('Content-Length: ' . strlen($pdf));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }
        echo $pdf;
        exit;
    }
}
