#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Génère la matrice de transport : backend/data/matrice_transport.sqlite
 *
 *   php backend/bin/generer-matrice.php
 *
 * Le fichier contient deux tables :
 *   quartiers  164 lignes  (id, nom, arrondissement, coordonnées)
 *   trajets    13 366 lignes = toutes les paires possibles, avec la distance
 *              et le prix de la course pour chaque mode.
 *
 * À relancer après toute modification de Pricing::BAREME.
 */

use LivraisonCm\Pricing;
use LivraisonCm\Text;

spl_autoload_register(static function (string $classe): void {
    $prefixe = 'LivraisonCm\\';
    if (!str_starts_with($classe, $prefixe)) {
        return;
    }
    $chemin = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
    if (is_file($chemin)) {
        require $chemin;
    }
});

$racine  = dirname(__DIR__);
$source  = $racine . '/data/quartiers.json';
$cible   = $racine . '/data/matrice_transport.sqlite';

$quartiers = json_decode((string) file_get_contents($source), true);
if (!is_array($quartiers) || $quartiers === []) {
    fwrite(STDERR, "Impossible de lire $source\n");
    exit(1);
}

if (is_file($cible)) {
    unlink($cible);
}
foreach (['-wal', '-shm'] as $suffixe) {
    if (is_file($cible . $suffixe)) {
        unlink($cible . $suffixe);
    }
}

$pdo = new PDO('sqlite:' . $cible);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE quartiers (
    id                INTEGER PRIMARY KEY,
    nom               TEXT NOT NULL,
    nom_recherche     TEXT NOT NULL,
    description       TEXT,
    arrondissement_id INTEGER NOT NULL,
    arrondissement    TEXT NOT NULL,
    lat               REAL NOT NULL,
    lon               REAL NOT NULL,
    precision_geo     TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE trajets (
    id                     INTEGER PRIMARY KEY,
    a_id                   INTEGER NOT NULL REFERENCES quartiers(id),
    b_id                   INTEGER NOT NULL REFERENCES quartiers(id),
    a_nom                  TEXT NOT NULL,
    b_nom                  TEXT NOT NULL,
    distance_vol_oiseau_km REAL NOT NULL,
    distance_route_km      REAL NOT NULL,
    traverse_pont          INTEGER NOT NULL DEFAULT 0,
    prix_moto              INTEGER NOT NULL,
    prix_taxi              INTEGER NOT NULL,
    prix_vtc               INTEGER NOT NULL,
    prix_taxi_ramassage    INTEGER NOT NULL
)');

$pdo->exec('CREATE UNIQUE INDEX idx_trajets_paire ON trajets(a_id, b_id)');

$pdo->exec('CREATE TABLE meta (cle TEXT PRIMARY KEY, valeur TEXT NOT NULL)');

// ------------------------------------------------------------------ quartiers

$insQ = $pdo->prepare('INSERT INTO quartiers VALUES (:id, :nom, :nr, :desc, :aid, :arr, :lat, :lon, :prec)');
$pdo->beginTransaction();
foreach ($quartiers as $q) {
    $insQ->execute([
        ':id'   => $q['id'],
        ':nom'  => $q['nom'],
        ':nr'   => Text::normalize($q['nom'] . ' ' . $q['arrondissement']),
        ':desc' => $q['description'],
        ':aid'  => $q['arrondissement_id'],
        ':arr'  => $q['arrondissement'],
        ':lat'  => $q['lat'],
        ':lon'  => $q['lon'],
        ':prec' => $q['precision'],
    ]);
}
$pdo->commit();

// -------------------------------------------------------------------- trajets

$insT = $pdo->prepare('INSERT INTO trajets
    (id, a_id, b_id, a_nom, b_nom, distance_vol_oiseau_km, distance_route_km, traverse_pont,
     prix_moto, prix_taxi, prix_vtc, prix_taxi_ramassage)
    VALUES (:id, :a, :b, :an, :bn, :vol, :route, :pont, :moto, :taxi, :vtc, :ram)');

$total = count($quartiers);
$id = 0;
$pdo->beginTransaction();

for ($i = 0; $i < $total; $i++) {
    for ($j = $i + 1; $j < $total; $j++) {
        $a = $quartiers[$i];
        $b = $quartiers[$j];

        // la paire est toujours rangée dans l'ordre croissant des identifiants
        $aId = min((int) $a['id'], (int) $b['id']);
        $bId = max((int) $a['id'], (int) $b['id']);

        $d = Pricing::distanceKm($a, $b);
        $id++;

        $insT->execute([
            ':id'    => $id,
            ':a'     => $aId,
            ':b'     => $bId,
            ':an'    => $aId === (int) $a['id'] ? $a['nom'] : $b['nom'],
            ':bn'    => $bId === (int) $b['id'] ? $b['nom'] : $a['nom'],
            ':vol'   => $d['vol_oiseau'],
            ':route' => $d['km'],
            ':pont'  => $d['pont'] ? 1 : 0,
            ':moto'  => Pricing::prixCourse('moto', $d['km'], $d['pont']),
            ':taxi'  => Pricing::prixCourse('taxi', $d['km'], $d['pont']),
            ':vtc'   => Pricing::prixCourse('vtc', $d['km'], $d['pont']),
            ':ram'   => Pricing::prixRamassage($d['km']),
        ]);
    }
}
$pdo->commit();

// ----------------------------------------------------------------------- meta

$insM = $pdo->prepare('INSERT INTO meta (cle, valeur) VALUES (:c, :v)');
foreach ([
    'genere_le'   => date('Y-m-d H:i:s'),
    'quartiers'   => (string) $total,
    'trajets'     => (string) $id,
    'bareme'      => json_encode(Pricing::BAREME, JSON_UNESCAPED_UNICODE),
    'devise'      => Pricing::BAREME['devise'],
] as $cle => $valeur) {
    $insM->execute([':c' => $cle, ':v' => $valeur]);
}

$pdo->exec('VACUUM');

printf(
    "Matrice écrite : %s\n  %d quartiers, %d trajets, %s\n",
    $cible,
    $total,
    $id,
    number_format(filesize($cible) / 1024, 0, ',', ' ') . ' Ko'
);
