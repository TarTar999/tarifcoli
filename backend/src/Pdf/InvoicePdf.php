<?php
declare(strict_types=1);

namespace LivraisonCm\Pdf;

use LivraisonCm\Text;

/** Compose l'attestation de prix TarifColi au format A4. */
final class InvoicePdf
{
    private const ENCRE   = '#0E1A14';   // vert-noir
    private const JAUNE   = '#FFC72C';   // jaune taxi de Douala
    private const VERT    = '#0F7A4F';
    private const ROUGE   = '#C8102E';   // encre du cachet
    private const GRIS    = '#6B7280';
    private const TRAIT   = '#D8DCD6';
    private const PAPIER  = '#F4F5F1';

    public static function render(array $facture, string $lienPartage): string
    {
        $pdf = new SimplePdf();
        $devis = $facture['devis'];

        $M = 42.0;                       // marge
        $W = $pdf->width();
        $droite = $W - $M;

        // ---------------------------------------------------------- bandeau
        $pdf->fillRect(0, 0, $W, 96, self::ENCRE);
        $pdf->fillRect(0, 96, $W, 4, self::JAUNE);

        $pdf->text($M, 34, 'Tarif', 22, 'HB', '#FFFFFF');
        $pdf->text($M + $pdf->textWidth('Tarif', 22, 'HB'), 34, 'Coli', 22, 'HB', self::JAUNE);
        $pdf->text($M, 58, 'Prix de livraison certifiés à Douala — 164 quartiers desservis', 8.5, 'H', '#B9C2BC');
        $pdf->text($M, 72, 'contact@tarifcoli.example  ·  +237 6 XX XX XX XX', 8.5, 'H', '#B9C2BC');

        $pdf->textRight($droite, 30, 'ATTESTATION DE PRIX', 9, 'HB', self::JAUNE);
        $pdf->textRight($droite, 48, $facture['numero'], 15, 'CB', '#FFFFFF');
        $pdf->textRight($droite, 66, 'Émis le ' . self::dateFr($facture['created_at']), 8.5, 'H', '#B9C2BC');
        $pdf->textRight($droite, 79, 'Code de vérification : ' . $facture['code'], 8.5, 'C', '#B9C2BC');

        // ------------------------------------------------------------ trajet
        $y = 132;
        $pdf->text($M, $y, 'TRAJET', 8, 'HB', self::GRIS);
        $y += 14;

        $largeur = ($droite - $M - 34) / 2;
        self::boiteQuartier($pdf, $M, $y, $largeur, 'ENLÈVEMENT', $devis['depart']);
        self::boiteQuartier($pdf, $M + $largeur + 34, $y, $largeur, 'LIVRAISON', $devis['arrivee']);

        // flèche entre les deux boîtes
        $cx = $M + $largeur + 17;
        $pdf->circle($cx, $y + 34, 11, self::JAUNE, self::JAUNE);
        $pdf->textCenter($cx, $y + 39, '>', 12, 'HB', self::ENCRE);

        $y += 92;

        // ----------------------------------------------------------- mission
        $infos = [
            ['Distance estimée', number_format($devis['distance_km'], 2, ',', ' ') . ' km'],
            ['Mode',             $devis['mode_libelle']],
            ['Délai indicatif',  $devis['delai']],
            ['Créneau',          $devis['creneau_libelle']],
        ];
        $col = ($droite - $M) / 4;
        $pdf->fillRect($M, $y, $droite - $M, 46, self::PAPIER);
        foreach ($infos as $i => [$k, $v]) {
            $x = $M + 14 + $i * $col;
            $pdf->text($x, $y + 17, Text::upper($k), 6.5, 'HB', self::GRIS);
            foreach ($pdf->wrap($v, $col - 18, 8.5, 'H') as $j => $ligne) {
                $pdf->text($x, $y + 30 + $j * 10, $ligne, 8.5, 'H', self::ENCRE);
            }
            if ($i > 0) {
                $pdf->line($x - 14, $y + 8, $x - 14, $y + 38, self::TRAIT, 0.6);
            }
        }
        $y += 68;

        // ------------------------------------------------------------- colis
        $client = $facture['client'];
        if (!empty($client['nom']) || !empty($client['tel']) || !empty($client['colis'])) {
            $pdf->text($M, $y, 'EXPÉDITEUR ET COLIS', 8, 'HB', self::GRIS);
            $y += 15;
            $ligneClient = array_filter([
                $client['nom'] ?: null,
                $client['tel'] ?: null,
                $client['colis'] ?: null,
                $devis['poids_kg'] > 0 ? number_format((float) $devis['poids_kg'], 1, ',', ' ') . ' kg' : null,
            ]);
            foreach ($pdf->wrap(implode('  ·  ', $ligneClient), $droite - $M, 9) as $i => $l) {
                $pdf->text($M, $y + $i * 12, $l, 9, 'H', self::ENCRE);
                $y += $i > 0 ? 0 : 0;
            }
            $y += 12 * max(1, count($pdf->wrap(implode('  ·  ', $ligneClient), $droite - $M, 9))) + 10;
        }

        // ----------------------------------------------------------- détail
        $pdf->text($M, $y, 'DÉTAIL DU PRIX', 8, 'HB', self::GRIS);
        $y += 16;

        $pdf->fillRect($M, $y, $droite - $M, 20, self::ENCRE);
        $pdf->text($M + 12, $y + 14, 'DÉSIGNATION', 7.5, 'HB', '#FFFFFF');
        $pdf->textRight($droite - 12, $y + 14, 'MONTANT', 7.5, 'HB', '#FFFFFF');
        $y += 20;

        foreach ($devis['lignes'] as $i => $ligne) {
            $hauteur = 34.0;
            if ($i % 2 === 1) {
                $pdf->fillRect($M, $y, $droite - $M, $hauteur, self::PAPIER);
            }
            $pdf->text($M + 12, $y + 14, $ligne['libelle'], 9.5, 'HB', self::ENCRE);
            $pdf->text($M + 12, $y + 26, $ligne['detail'], 7.5, 'H', self::GRIS);
            $pdf->textRight($droite - 12, $y + 18, number_format($ligne['montant'], 0, ',', ' '), 10.5, 'C', self::ENCRE);
            $pdf->line($M, $y + $hauteur, $droite, $y + $hauteur, self::TRAIT, 0.5);
            $y += $hauteur;
        }

        // ------------------------------------------------------------- total
        $y += 16;
        $largeurTotal = 250.0;
        $xTotal = $droite - $largeurTotal;
        $pdf->fillRect($xTotal, $y, $largeurTotal, 58, self::ENCRE);
        $pdf->fillRect($xTotal, $y, 5, 58, self::JAUNE);
        $pdf->text($xTotal + 18, $y + 20, 'PRIX CERTIFIÉ', 8, 'HB', self::JAUNE);
        $pdf->textRight($droite - 16, $y + 30, number_format($facture['total'], 0, ',', ' ') . ' FCFA', 19, 'CB', '#FFFFFF');
        $pdf->text($M, $y + 18, sprintf('Prix garanti %d h après émission', \LivraisonCm\Invoice::VALIDITE_HEURES), 9, 'HB', self::ENCRE);
        $pdf->text($M, $y + 31, 'Règlement à la livraison : espèces · MTN MoMo · Orange Money', 8.5, 'H', self::GRIS);
        $pdf->text($M, $y + 44, "Ce document atteste d'un prix, il ne vaut pas reçu de paiement.", 8, 'H', self::GRIS);
        $y += 58;

        // ------------------------------------------------------------ cachet
        self::cachet($pdf, $M + 92, $y + 86, $facture);

        // ------------------------------------------------------- vérification
        $yv = $y + 44;
        $xv = $M + 210;
        $pdf->text($xv, $yv, 'VÉRIFIER CETTE ATTESTATION', 7.5, 'HB', self::GRIS);
        $pdf->strokeRect($xv, $yv + 8, $droite - $xv, 46, self::TRAIT, 0.8);
        foreach ($pdf->wrap($lienPartage, $droite - $xv - 20, 8.5, 'C') as $i => $l) {
            $pdf->text($xv + 10, $yv + 26 + $i * 12, $l, 8.5, 'C', self::VERT);
        }
        $pdf->text($xv, $yv + 70, "Le code de vérification permet de retrouver cette attestation en ligne", 7.5, 'H', self::GRIS);
        $pdf->text($xv, $yv + 82, 'et de confirmer que le prix affiché est bien celui émis par TarifColi.', 7.5, 'H', self::GRIS);
        $pdf->text($xv, $yv + 96, 'Adressage et calcul de trajet : SomeWhere App.', 7.5, 'HB', self::VERT);

        // ------------------------------------------------------------- notes
        $yn = 730.0;
        if (!empty($devis['alertes'])) {
            $pdf->text($M, $yn, 'À SAVOIR', 7.5, 'HB', self::GRIS);
            $yn += 13;
            foreach ($devis['alertes'] as $a) {
                foreach ($pdf->wrap('· ' . $a, $droite - $M, 7.5) as $l) {
                    $pdf->text($M, $yn, $l, 7.5, 'H', self::GRIS);
                    $yn += 10;
                }
            }
        }

        // ------------------------------------------------------------- pied
        $pdf->line($M, 795, $droite, 795, self::TRAIT, 0.8);
        $pdf->text($M, 810, 'TarifColi', 8.5, 'HB', self::ENCRE);
        $pdf->text($M + 62, 810, '· Douala, Cameroun · Prix en francs CFA, taxes comprises', 8, 'H', self::GRIS);
        $pdf->textRight($droite, 810, $facture['numero'] . ' · page 1/1', 8, 'C', self::GRIS);

        $pdf->textCenter($W / 2, 824, 'Powered by SomeWhere App', 7.5, 'HB', self::VERT);

        return $pdf->output();
    }

    private static function boiteQuartier(SimplePdf $pdf, float $x, float $y, float $w, string $titre, array $q): void
    {
        $pdf->strokeRect($x, $y, $w, 76, self::TRAIT, 0.8);
        $pdf->fillRect($x, $y, 4, 76, $titre === 'ENLÈVEMENT' ? self::VERT : self::JAUNE);
        $pdf->text($x + 16, $y + 18, $titre, 7, 'HB', self::GRIS);
        $pdf->text($x + 16, $y + 38, $q['nom'], 13, 'HB', self::ENCRE);
        $pdf->text($x + 16, $y + 52, $q['arrondissement'], 8, 'H', self::GRIS);

        $desc = $pdf->wrap((string) ($q['description'] ?? ''), $w - 32, 7.5);
        if ($desc) {
            $pdf->text($x + 16, $y + 66, $desc[0], 7.5, 'H', self::GRIS);
        }
    }

    /** Le cachet de certification : deux cercles, texte pivoté, code de contrôle. */
    private static function cachet(SimplePdf $pdf, float $cx, float $cy, array $facture): void
    {
        $r = 52.0;

        $pdf->circle($cx, $cy, $r, self::ROUGE, null, 2.4);
        $pdf->circle($cx, $cy, $r - 7, self::ROUGE, null, 0.8);

        // légèrement de travers, comme un tampon posé à la main
        $angle = -8.0;
        $pdf->textRotated($cx, $cy - 28, 'TARIFCOLI', $angle, 10, 'HB', self::ROUGE, true);
        $pdf->textRotated($cx, $cy - 16, 'DOUALA · CAMEROUN', $angle, 6, 'H', self::ROUGE, true);
        $pdf->line($cx - 30, $cy - 7, $cx + 29, $cy - 11, self::ROUGE, 0.7);
        $pdf->textRotated($cx, $cy + 1, 'PRIX CERTIFIÉ', $angle, 7.5, 'HB', self::ROUGE, true);
        $pdf->textRotated($cx, $cy + 13, substr($facture['created_at'], 0, 10), $angle, 7.5, 'C', self::ROUGE, true);
        $pdf->textRotated($cx, $cy + 26, $facture['code'], $angle, 9, 'CB', self::ROUGE, true);
    }

    private static function dateFr(string $sql): string
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $sql) ?: new \DateTimeImmutable();
        $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return sprintf('%s %s %s à %s', $d->format('j'), $mois[(int) $d->format('n') - 1], $d->format('Y'), $d->format('H\hi'));
    }
}
