<?php
declare(strict_types=1);

namespace LivraisonCm;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;
    private static bool $matrice = false;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dir = dirname(__DIR__) . '/storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $dir . '/livraison.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');

        // La matrice de transport est un fichier SQLite séparé, rattaché à la
        // connexion : les prix se lisent avec « SELECT ... FROM matrice.trajets ».
        $matrice = dirname(__DIR__) . '/data/matrice_transport.sqlite';
        if (is_file($matrice)) {
            $pdo->exec("ATTACH DATABASE '" . str_replace("'", "''", $matrice) . "' AS matrice");
            self::$matrice = true;
        }

        self::$pdo = $pdo;
        self::migrate($pdo);
        self::seed($pdo);

        return $pdo;
    }

    /** La matrice est-elle rattachée à la connexion ? */
    public static function matriceDisponible(): bool
    {
        self::pdo();

        return self::$matrice;
    }

    /** Compteurs de la matrice, pour /api/sante. */
    public static function statsMatrice(): array
    {
        if (!self::matriceDisponible()) {
            return ['disponible' => false];
        }

        $pdo = self::pdo();
        $meta = [];
        foreach ($pdo->query('SELECT cle, valeur FROM matrice.meta') as $r) {
            $meta[$r['cle']] = $r['valeur'];
        }

        return [
            'disponible' => true,
            'fichier'    => 'backend/data/matrice_transport.sqlite',
            'quartiers'  => (int) $pdo->query('SELECT COUNT(*) FROM matrice.quartiers')->fetchColumn(),
            'trajets'    => (int) $pdo->query('SELECT COUNT(*) FROM matrice.trajets')->fetchColumn(),
            'genere_le'  => $meta['genere_le'] ?? null,
        ];
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS quartiers (
            id                INTEGER PRIMARY KEY,
            nom               TEXT NOT NULL,
            nom_recherche     TEXT NOT NULL,
            description       TEXT,
            arrondissement_id INTEGER NOT NULL,
            arrondissement    TEXT NOT NULL,
            lat               REAL NOT NULL,
            lon               REAL NOT NULL,
            precision_geo     TEXT NOT NULL DEFAULT 'approx'
        );
        SQL);

        $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS factures (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            code            TEXT NOT NULL UNIQUE,
            numero          TEXT NOT NULL UNIQUE,
            depart_id       INTEGER NOT NULL REFERENCES quartiers(id),
            arrivee_id      INTEGER NOT NULL REFERENCES quartiers(id),
            mode            TEXT NOT NULL,
            creneau         TEXT NOT NULL,
            poids_kg        REAL NOT NULL DEFAULT 0,
            colis           TEXT,
            client_nom      TEXT,
            client_tel      TEXT,
            distance_km     REAL NOT NULL,
            total_fcfa      INTEGER NOT NULL,
            detail_json     TEXT NOT NULL,
            created_at      TEXT NOT NULL
        );
        SQL);

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_factures_code ON factures(code)');
    }

    private static function seed(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM quartiers')->fetchColumn();
        if ($count > 0) {
            return;
        }

        // source préférée : la matrice (elle fait foi pour les identifiants)
        if (self::$matrice) {
            $pdo->exec('INSERT INTO quartiers
                        SELECT id, nom, nom_recherche, description, arrondissement_id,
                               arrondissement, lat, lon, precision_geo
                        FROM matrice.quartiers');

            return;
        }

        $rows = json_decode((string) file_get_contents(dirname(__DIR__) . '/data/quartiers.json'), true);
        $stmt = $pdo->prepare(
            'INSERT INTO quartiers (id, nom, nom_recherche, description, arrondissement_id, arrondissement, lat, lon, precision_geo)
             VALUES (:id, :nom, :nr, :desc, :aid, :arr, :lat, :lon, :prec)'
        );

        $pdo->beginTransaction();
        foreach ($rows as $r) {
            $stmt->execute([
                ':id'   => $r['id'],
                ':nom'  => $r['nom'],
                ':nr'   => Text::normalize($r['nom'] . ' ' . $r['arrondissement']),
                ':desc' => $r['description'],
                ':aid'  => $r['arrondissement_id'],
                ':arr'  => $r['arrondissement'],
                ':lat'  => $r['lat'],
                ':lon'  => $r['lon'],
                ':prec' => $r['precision'],
            ]);
        }
        $pdo->commit();
    }
}
