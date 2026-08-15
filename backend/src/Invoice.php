<?php
declare(strict_types=1);

namespace LivraisonCm;

final class Invoice
{
    /** Durée pendant laquelle le prix attesté est garanti. */
    public const VALIDITE_HEURES = 24;

    public static function create(array $devis, array $client): array
    {
        $pdo = Db::pdo();
        $code = self::code();
        $numero = self::numero($pdo);
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Douala')))->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO factures
             (code, numero, depart_id, arrivee_id, mode, creneau, poids_kg, colis, client_nom, client_tel,
              distance_km, total_fcfa, detail_json, created_at)
             VALUES (:code, :numero, :dep, :arr, :mode, :creneau, :poids, :colis, :nom, :tel,
              :dist, :total, :detail, :created)'
        );

        $stmt->execute([
            ':code'    => $code,
            ':numero'  => $numero,
            ':dep'     => $devis['depart']['id'],
            ':arr'     => $devis['arrivee']['id'],
            ':mode'    => $devis['mode'],
            ':creneau' => $devis['creneau'],
            ':poids'   => $devis['poids_kg'],
            ':colis'   => $client['colis'] ?? null,
            ':nom'     => $client['nom'] ?? null,
            ':tel'     => $client['tel'] ?? null,
            ':dist'    => $devis['distance_km'],
            ':total'   => $devis['total'],
            ':detail'  => json_encode($devis, JSON_UNESCAPED_UNICODE),
            ':created' => $now,
        ]);

        return self::findByCode($code) ?? [];
    }

    public static function findByCode(string $code): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM factures WHERE code = :c');
        $stmt->execute([':c' => strtoupper($code)]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return [
            'code'        => $row['code'],
            'numero'      => $row['numero'],
            'created_at'  => $row['created_at'],
            'client'      => [
                'nom'   => $row['client_nom'],
                'tel'   => $row['client_tel'],
                'colis' => $row['colis'],
            ],
            'distance_km' => (float) $row['distance_km'],
            'total'       => (int) $row['total_fcfa'],
            'devis'       => json_decode($row['detail_json'], true),
        ];
    }

    /** Code court, lisible au téléphone, sans caractères ambigus (0/O, 1/I). */
    private static function code(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $stmt = Db::pdo()->prepare('SELECT 1 FROM factures WHERE code = :c');
            $stmt->execute([':c' => $code]);
        } while ($stmt->fetchColumn());

        return $code;
    }

    private static function numero(\PDO $pdo): string
    {
        $annee = date('Y');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM factures WHERE numero LIKE :p");
        $stmt->execute([':p' => 'ATT-' . $annee . '-%']);
        $n = (int) $stmt->fetchColumn() + 1;

        return sprintf('ATT-%s-%05d', $annee, $n);
    }
}
