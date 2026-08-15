<?php
declare(strict_types=1);

namespace LivraisonCm;

/**
 * Tarification TarifColi.
 *
 * Le prix de la course vient de la MATRICE SQLite (backend/data/matrice_transport.sqlite,
 * table matrice.trajets : 13 366 paires de quartiers). Le calcul géométrique ci-dessous
 * ne sert qu'à deux choses : générer cette matrice (bin/generer-matrice.php) et servir
 * de secours si une paire venait à manquer.
 *
 * BAREME est le seul endroit à modifier pour recaler les prix. Après modification :
 *     php backend/bin/generer-matrice.php
 */
final class Pricing
{
    public const BAREME = [
        'modes' => [
            'moto' => [
                'libelle'    => 'Moto (livraison express)',
                'colonne'    => 'prix_moto',
                'prestation' => 200,   // rémunération du livreur
                'prise'      => 300,   // prise en charge FCFA
                'km'         => 120,   // FCFA par km
                'minimum'    => 700,
                'pont'       => 0,     // les motos ne franchissent pas le pont du Wouri
                'pont_ok'    => false,
                'delai'      => '30 à 60 min',
                'poids_max'  => 25,
            ],
            // taxi partagé : on paie 350 F par tronçon, comme un passager ordinaire
            'ramassage' => [
                'libelle'    => 'Taxi partagé (ramassage)',
                'colonne'    => 'prix_taxi_ramassage',
                'prestation' => 150,
                'prise'      => 0,
                'km'         => 0,
                'minimum'    => 350,
                'pont'       => 0,
                'pont_ok'    => true,
                'delai'      => '1 h à 2 h (correspondances)',
                'poids_max'  => 15,
            ],
            'taxi' => [
                'libelle'    => 'Taxi course (colis volumineux)',
                'colonne'    => 'prix_taxi',
                'prestation' => 0,     // le chauffeur accompagne le colis
                'prise'      => 900,
                'km'         => 230,
                'minimum'    => 1500,
                'pont'       => 1000,
                'pont_ok'    => true,
                'delai'      => '45 à 90 min',
                'poids_max'  => 80,
            ],
            'vtc' => [
                'libelle'    => 'VTC (course dédiée, suivi)',
                'colonne'    => 'prix_vtc',
                'prestation' => 0,
                'prise'      => 700,
                'km'         => 320,
                'minimum'    => 1300,
                'pont'       => 500,
                'pont_ok'    => true,
                'delai'      => '30 à 60 min',
                'poids_max'  => 60,
            ],
        ],
        'creneaux' => [
            'journee' => ['libelle' => 'Journée (9h–17h)', 'coef' => 1.0],
            'pointe'  => ['libelle' => 'Heure de pointe (6h–9h / 17h–20h)', 'coef' => 1.2],
            'nuit'    => ['libelle' => 'Nuit (après 22h)', 'coef' => 1.3],
        ],
        'poids' => [
            ['max' => 5,    'supplement' => 0],
            ['max' => 20,   'supplement' => 500],
            ['max' => 1000, 'supplement' => 1500],
        ],
        // taxi partagé : 350 F le tronçon, un tronçon de plus à chaque correspondance
        'ramassage'     => ['tarif' => 350, 'seuils' => [4, 9, 15, 22]],
        'coef_detour'   => 1.35,       // distance route ≈ vol d'oiseau × 1,35
        'arrondi'       => 50,         // les prix se règlent au billet de 50 F
        'pont_wouri'    => ['lat' => 4.0555, 'lon' => 9.6870],
        'devise'        => 'FCFA',
    ];

    // ------------------------------------------------------------- la matrice

    /** Lit une paire dans la matrice SQLite. Renvoie null si la matrice est absente. */
    public static function trajet(int $idA, int $idB): ?array
    {
        if (!Db::matriceDisponible()) {
            return null;
        }

        $stmt = Db::pdo()->prepare('SELECT * FROM matrice.trajets WHERE a_id = :a AND b_id = :b');
        $stmt->execute([':a' => min($idA, $idB), ':b' => max($idA, $idB)]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    // -------------------------------------------------------------- le calcul

    public static function distanceKm(array $a, array $b): array
    {
        $pont = ($a['arrondissement_id'] === 4) !== ($b['arrondissement_id'] === 4);
        $p = self::BAREME['pont_wouri'];
        $vol = self::haversine($a['lat'], $a['lon'], $b['lat'], $b['lon']);

        if ($pont) {
            $km = (self::haversine($a['lat'], $a['lon'], $p['lat'], $p['lon'])
                 + self::haversine($p['lat'], $p['lon'], $b['lat'], $b['lon'])) * 1.25;
        } else {
            $km = $vol * self::BAREME['coef_detour'];
        }

        return ['km' => round($km, 2), 'vol_oiseau' => round($vol, 2), 'pont' => $pont];
    }

    /** Prix d'une course, supplément de pont compris. Sert à remplir la matrice. */
    public static function prixCourse(string $mode, float $km, bool $pont): int
    {
        if ($mode === 'ramassage') {
            return self::prixRamassage($km);
        }

        $m = self::BAREME['modes'][$mode] ?? self::BAREME['modes']['moto'];
        $prix = max($m['minimum'], $m['prise'] + $m['km'] * $km) + ($pont ? $m['pont'] : 0);

        return self::arrondir($prix);
    }

    /** Taxi partagé : 350 F par tronçon, un tronçon de plus à chaque correspondance. */
    public static function prixRamassage(float $km): int
    {
        $r = self::BAREME['ramassage'];
        $troncons = count($r['seuils']) + 1;
        foreach ($r['seuils'] as $i => $seuil) {
            if ($km <= $seuil) {
                $troncons = $i + 1;
                break;
            }
        }

        return $troncons * $r['tarif'];
    }

    // --------------------------------------------------------------- le devis

    public static function quote(array $depart, array $arrivee, string $mode, string $creneau, float $poids): array
    {
        $modes = self::BAREME['modes'];
        if (!isset($modes[$mode])) {
            $mode = 'moto';
        }
        if (!isset(self::BAREME['creneaux'][$creneau])) {
            $creneau = 'journee';
        }

        $m = $modes[$mode];

        // 1. le prix de la course vient de la matrice
        $trajet = self::trajet($depart['id'], $arrivee['id']);

        if ($trajet !== null) {
            $km     = (float) $trajet['distance_route_km'];
            $pont   = (bool) $trajet['traverse_pont'];
            $base   = (int) $trajet[$m['colonne']];
            $source = 'matrice';
            $ref    = '#' . $trajet['id'];
            $detail = sprintf(
                'Tarif matrice, trajet %s · %s km%s%s',
                $ref,
                number_format($km, 2, ',', ' '),
                $mode === 'ramassage'
                    ? sprintf(' · %d tronçon(s) à %s F', max(1, (int) round($base / self::BAREME['ramassage']['tarif'])), self::BAREME['ramassage']['tarif'])
                    : '',
                $pont && $mode !== 'ramassage' ? ' · traversée du pont comprise' : ''
            );
        } else {
            $d      = self::distanceKm($depart, $arrivee);
            $km     = $d['km'];
            $pont   = $d['pont'];
            $base   = self::prixCourse($mode, $km, $pont);
            $source = 'calcul';
            $ref    = null;
            $detail = $mode === 'ramassage'
                ? sprintf(
                    'Calcul de secours : %s F le tronçon × %d · %s km',
                    self::BAREME['ramassage']['tarif'],
                    max(1, (int) round($base / self::BAREME['ramassage']['tarif'])),
                    number_format($km, 2, ',', ' ')
                )
                : sprintf(
                    'Calcul de secours : %s F + %s F/km × %s km%s',
                    $m['prise'],
                    $m['km'],
                    number_format($km, 2, ',', ' '),
                    $pont ? ' + pont du Wouri' : ''
                );
        }

        $lignes = [[
            'libelle' => 'Course ' . $m['libelle'],
            'detail'  => $detail,
            'montant' => $base,
        ]];

        // 2. prestation du livreur
        $prestation = (int) ($m['prestation'] ?? 0);
        if ($prestation > 0) {
            $lignes[] = [
                'libelle' => 'Prestation livreur',
                'detail'  => 'Prise en charge du colis et remise au destinataire',
                'montant' => $prestation,
            ];
        }

        // 3. majoration horaire
        $coef = self::BAREME['creneaux'][$creneau]['coef'];
        if ($coef > 1.0) {
            $lignes[] = [
                'libelle' => 'Majoration ' . self::BAREME['creneaux'][$creneau]['libelle'],
                'detail'  => sprintf('+%d %% sur la course', (int) round(($coef - 1) * 100)),
                'montant' => (int) round($base * ($coef - 1)),
            ];
        }

        // 4. supplément de poids
        $supPoids = 0;
        foreach (self::BAREME['poids'] as $palier) {
            if ($poids <= $palier['max']) {
                $supPoids = $palier['supplement'];
                break;
            }
        }
        if ($supPoids > 0) {
            $lignes[] = [
                'libelle' => 'Supplément colis',
                'detail'  => sprintf('Poids déclaré : %s kg', number_format($poids, 1, ',', ' ')),
                'montant' => $supPoids,
            ];
        }

        $total = self::arrondir((float) array_sum(array_column($lignes, 'montant')));

        $alertes = [];
        if ($pont && !$m['pont_ok']) {
            $alertes[] = "Les motos ne sont pas autorisées sur le pont du Wouri : ce trajet sera assuré en taxi ou en VTC.";
        }
        if ($poids > $m['poids_max']) {
            $alertes[] = sprintf('Poids supérieur à la capacité du mode choisi (%s kg).', $m['poids_max']);
        }
        if ($source === 'calcul') {
            $alertes[] = "Ce trajet ne figure pas dans la matrice : prix calculé à la volée. Régénérez-la avec bin/generer-matrice.php.";
        }
        $alertes[] = "Distance mesurée entre les centres des quartiers. Avec une adresse SomeWhere App de part et d'autre, le trajet est calculé au point près et le prix est plus juste.";

        return [
            'depart'          => $depart,
            'arrivee'         => $arrivee,
            'mode'            => $mode,
            'mode_libelle'    => $m['libelle'],
            'delai'           => $m['delai'],
            'creneau'         => $creneau,
            'creneau_libelle' => self::BAREME['creneaux'][$creneau]['libelle'],
            'poids_kg'        => $poids,
            'distance_km'     => $km,
            'traverse_pont'   => $pont,
            'source_prix'     => $source,   // 'matrice' ou 'calcul'
            'trajet_ref'      => $ref,      // identifiant de la ligne de matrice
            'prix_matrice'    => $trajet ? [
                'moto'                   => (int) $trajet['prix_moto'],
                'taxi'                   => (int) $trajet['prix_taxi'],
                'vtc'                    => (int) $trajet['prix_vtc'],
                'ramassage'              => (int) $trajet['prix_taxi_ramassage'],
                'distance_vol_oiseau_km' => (float) $trajet['distance_vol_oiseau_km'],
            ] : null,
            'lignes'          => $lignes,
            'total'           => $total,
            'devise'          => self::BAREME['devise'],
            'alertes'         => $alertes,
        ];
    }

    // --------------------------------------------------------------- interne

    private static function arrondir(float $montant): int
    {
        $pas = self::BAREME['arrondi'];

        return (int) (ceil($montant / $pas) * $pas);
    }

    private static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}
