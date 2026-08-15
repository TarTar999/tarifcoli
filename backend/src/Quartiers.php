<?php
declare(strict_types=1);

namespace LivraisonCm;

final class Quartiers
{
    /** Autocomplétion : commence-par d'abord, contient ensuite. */
    public static function search(string $q, int $limit = 8): array
    {
        $pdo = Db::pdo();
        $q = Text::normalize($q);

        if ($q === '') {
            $stmt = $pdo->prepare('SELECT * FROM quartiers ORDER BY nom LIMIT :l');
            $stmt->bindValue(':l', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return array_map([self::class, 'shape'], $stmt->fetchAll());
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM quartiers
             WHERE nom_recherche LIKE :starts OR nom_recherche LIKE :contains
             ORDER BY CASE WHEN nom_recherche LIKE :starts THEN 0 ELSE 1 END, length(nom), nom
             LIMIT :l'
        );
        $stmt->bindValue(':starts', $q . '%');
        $stmt->bindValue(':contains', '%' . $q . '%');
        $stmt->bindValue(':l', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([self::class, 'shape'], $stmt->fetchAll());
    }

    public static function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM quartiers WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::shape($row) : null;
    }

    private static function shape(array $r): array
    {
        return [
            'id'                => (int) $r['id'],
            'nom'               => $r['nom'],
            'description'       => $r['description'],
            'arrondissement_id' => (int) $r['arrondissement_id'],
            'arrondissement'    => $r['arrondissement'],
            'lat'               => (float) $r['lat'],
            'lon'               => (float) $r['lon'],
            'precision'         => $r['precision_geo'],
        ];
    }
}
