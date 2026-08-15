<?php
declare(strict_types=1);

/**
 * TarifColi — point d'entrée unique.
 *
 * Développement :  php -S localhost:8000 -t backend/public
 * Production    :  faire pointer le DocumentRoot du serveur sur ce dossier.
 */

use LivraisonCm\Invoice;
use LivraisonCm\Pdf\InvoicePdf;
use LivraisonCm\Pricing;
use LivraisonCm\Quartiers;

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

date_default_timezone_set('Africa/Douala');

$methode = $_SERVER['REQUEST_METHOD'];
$chemin  = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';

header('X-Content-Type-Options: nosniff');
if (str_starts_with($chemin, '/api/')) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    if ($methode === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function json_out(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function corps(): array
{
    $brut = file_get_contents('php://input') ?: '';
    $data = json_decode($brut, true);

    return is_array($data) ? $data : $_POST;
}

function base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443';

    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000');
}

/** Construit un devis à partir du corps de la requête. */
function devis_depuis(array $in): array
{
    $depart  = Quartiers::find((int) ($in['depart_id'] ?? 0));
    $arrivee = Quartiers::find((int) ($in['arrivee_id'] ?? 0));

    if (!$depart || !$arrivee) {
        json_out(['erreur' => 'Choisissez un quartier de départ et un quartier d\'arrivée dans la liste.'], 422);
    }
    if ($depart['id'] === $arrivee['id']) {
        json_out(['erreur' => 'Le départ et l\'arrivée sont dans le même quartier. Choisissez deux quartiers différents.'], 422);
    }

    return Pricing::quote(
        $depart,
        $arrivee,
        (string) ($in['mode'] ?? 'moto'),
        (string) ($in['creneau'] ?? 'journee'),
        max(0.0, (float) ($in['poids_kg'] ?? 0))
    );
}

// ------------------------------------------------------------------- routes

if ($chemin === '/api/quartiers' && $methode === 'GET') {
    json_out(['resultats' => Quartiers::search((string) ($_GET['q'] ?? ''), min(20, max(1, (int) ($_GET['limit'] ?? 8))))]);
}

if ($chemin === '/api/sante' && $methode === 'GET') {
    json_out([
        'service'  => 'TarifColi',
        'matrice'  => LivraisonCm\Db::statsMatrice(),
    ]);
}

if (preg_match('#^/api/matrice/(\d+)/(\d+)$#', $chemin, $m) && $methode === 'GET') {
    $trajet = Pricing::trajet((int) $m[1], (int) $m[2]);
    if (!$trajet) {
        json_out(['erreur' => "Cette paire de quartiers n'est pas dans la matrice."], 404);
    }
    json_out(['trajet' => $trajet]);
}

if ($chemin === '/api/bareme' && $methode === 'GET') {
    json_out([
        'modes'    => array_map(
            static fn ($k, $m) => ['cle' => $k, 'libelle' => $m['libelle'], 'delai' => $m['delai'], 'poids_max' => $m['poids_max']],
            array_keys(Pricing::BAREME['modes']),
            Pricing::BAREME['modes']
        ),
        'creneaux' => array_map(
            static fn ($k, $c) => ['cle' => $k, 'libelle' => $c['libelle'], 'coef' => $c['coef']],
            array_keys(Pricing::BAREME['creneaux']),
            Pricing::BAREME['creneaux']
        ),
        'ramassage'     => Pricing::BAREME['ramassage'],
    ]);
}

if ($chemin === '/api/devis' && $methode === 'POST') {
    json_out(['devis' => devis_depuis(corps())]);
}

if (($chemin === '/api/attestations' || $chemin === '/api/factures') && $methode === 'POST') {
    $in = corps();
    $devis = devis_depuis($in);

    $facture = Invoice::create($devis, [
        'nom'   => trim((string) ($in['client_nom'] ?? '')) ?: null,
        'tel'   => trim((string) ($in['client_tel'] ?? '')) ?: null,
        'colis' => trim((string) ($in['colis'] ?? '')) ?: null,
    ]);

    json_out([
        'facture'     => $facture,
        'lien_partage'=> base_url() . '/a/' . $facture['code'],
        'lien_pdf'    => base_url() . '/api/attestations/' . $facture['code'] . '/pdf',
    ], 201);
}

if (preg_match('#^/api/(?:attestations|factures)/([A-Za-z0-9]{6,12})(/pdf)?$#', $chemin, $m) && $methode === 'GET') {
    $facture = Invoice::findByCode($m[1]);
    if (!$facture) {
        json_out(['erreur' => "Aucune attestation ne correspond à ce code."], 404);
    }

    if (($m[2] ?? '') === '/pdf') {
        $pdf = InvoicePdf::render($facture, base_url() . '/a/' . $facture['code']);
        $telechargement = ($_GET['inline'] ?? '') === '1' ? 'inline' : 'attachment';

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $telechargement . '; filename="' . $facture['numero'] . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
        exit;
    }

    json_out([
        'facture'      => $facture,
        'lien_partage' => base_url() . '/a/' . $facture['code'],
        'lien_pdf'     => base_url() . '/api/attestations/' . $facture['code'] . '/pdf',
    ]);
}

// page publique de partage
if (preg_match('#^/(?:a|f)/([A-Za-z0-9]{6,12})$#', $chemin, $m) && $methode === 'GET') {
    $facture = Invoice::findByCode($m[1]);
    http_response_code($facture ? 200 : 404);
    header('Content-Type: text/html; charset=utf-8');
    require dirname(__DIR__) . '/views/partage.php';
    exit;
}

// ------------------------------------------------- fichiers statiques (logo, PWA)
$public = dirname(__DIR__) . '/public';
$types = [
    'js' => 'text/javascript', 'css' => 'text/css', 'html' => 'text/html',
    'svg' => 'image/svg+xml', 'png' => 'image/png', 'webp' => 'image/webp',
    'woff2' => 'font/woff2', 'json' => 'application/json', 'ico' => 'image/x-icon',
    'webmanifest' => 'application/manifest+json',
];

// Fichiers PWA et logos à la racine public
$fichierPublic = realpath($public . $chemin);
if ($fichierPublic && is_file($fichierPublic) && str_starts_with($fichierPublic, realpath($public) ?: '///')) {
    $ext = strtolower(pathinfo($fichierPublic, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    readfile($fichierPublic);
    exit;
}

// ------------------------------------------------- front React compilé (SPA)
$app = $public . '/app';
$fichier = realpath($app . $chemin);

if ($fichier && is_file($fichier) && str_starts_with($fichier, realpath($app) ?: '///')) {
    $ext = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    readfile($fichier);
    exit;
}

if (is_file($app . '/index.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($app . '/index.html');
    exit;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><body style="font:16px system-ui;padding:40px">'
   . '<h1>TarifColi</h1><p>L\'API tourne. Compilez le front (<code>npm run build</code> dans <code>frontend/</code>) '
   . 'ou lancez <code>npm run dev</code> pour l\'interface.</p></body>';
