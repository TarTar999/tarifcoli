<?php
/** @var array|null $facture */
$e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fcfa = static fn (int $n): string => number_format($n, 0, ',', ' ') . ' FCFA';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0E1A14">
<link rel="icon" type="image/svg+xml" href="/logo/logo_icon_vert.svg">
<link rel="apple-touch-icon" sizes="180x180" href="/logo/icon-180.png">
<?php
$pageTitle = $facture ? 'Attestation ' . $e($facture['numero']) : 'Attestation introuvable';
$ogTitle = $facture
    ? 'Livraison ' . $e($facture['devis']['depart']['nom']) . ' → ' . $e($facture['devis']['arrivee']['nom'])
    : 'Attestation introuvable';
$ogDescription = $facture
    ? 'Montant : ' . $fcfa($facture['total']) . ' · De ' . $e($facture['devis']['depart']['nom']) . ' à ' . $e($facture['devis']['arrivee']['nom']) . ' · ' . number_format($facture['devis']['distance_km'], 1, ',', ' ') . ' km · Attestation certifiée TarifColi'
    : 'Ce code ne correspond à aucune attestation de prix.';
?>
<title><?= $pageTitle ?> · TarifColi</title>
<meta name="description" content="<?= $e($ogDescription) ?>">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="article" />
<meta property="og:title" content="<?= $e($ogTitle) ?>" />
<meta property="og:description" content="<?= $e($ogDescription) ?>" />
<meta property="og:site_name" content="TarifColi" />
<meta property="og:locale" content="fr_CM" />
<meta property="og:image" content="/logo/logo_long_vert_noir.png" />
<?php if ($facture): ?>
<meta property="article:published_time" content="<?= $e($facture['created_at']) ?>" />
<?php endif; ?>

<!-- Twitter Card -->
<meta name="twitter:card" content="summary" />
<meta name="twitter:title" content="<?= $e($ogTitle) ?>" />
<meta name="twitter:description" content="<?= $e($ogDescription) ?>" />
<meta name="twitter:image" content="/logo/logo_long_vert_noir.png" />

<!-- Branding -->
<meta name="author" content="Powered by SomeWhere App" />
<meta name="generator" content="SomeWhere App" />
<style>
  :root{--encre:#0E1A14;--jaune:#FFC72C;--vert:#0F7A4F;--gris:#6B7280;--papier:#F4F5F1;--trait:#D8DCD6}
  *{box-sizing:border-box}
  body{margin:0;background:var(--papier);color:var(--encre);
       font:15px/1.55 ui-sans-serif,system-ui,"Segoe UI",Roboto,sans-serif}
  .wrap{max-width:640px;margin:0 auto;padding:24px 18px 64px}
  .card{background:#fff;border:1px solid var(--trait);border-radius:14px;overflow:hidden}
  .head{background:var(--encre);color:#fff;padding:20px 22px}
  .head img{height:28px}
  .head b{font-size:20px;letter-spacing:-.02em}
  .head b i{color:var(--jaune);font-style:normal}
  .num{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;color:#B9C2BC;margin-top:4px}
  .body{padding:22px}
  .route{display:flex;align-items:center;gap:12px;margin-bottom:18px}
  .q{flex:1;border:1px solid var(--trait);border-radius:10px;padding:12px}
  .q span{display:block;font-size:11px;letter-spacing:.08em;color:var(--gris)}
  .q strong{font-size:17px}
  .q em{display:block;font-style:normal;font-size:12px;color:var(--gris)}
  .dot{width:26px;height:26px;border-radius:50%;background:var(--jaune);display:grid;place-items:center;font-weight:700;flex:0 0 auto}
  table{width:100%;border-collapse:collapse;margin-top:6px}
  td{padding:10px 0;border-bottom:1px solid var(--trait);vertical-align:top}
  td.m{text-align:right;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;white-space:nowrap}
  td small{display:block;color:var(--gris)}
  .total{display:flex;justify-content:space-between;align-items:center;background:var(--encre);color:#fff;
         border-radius:10px;padding:14px 18px;margin-top:16px}
  .total b{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:22px}
  .total span{font-size:12px;letter-spacing:.08em;color:var(--jaune)}
  .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
  a.btn{flex:1;min-width:200px;text-align:center;text-decoration:none;padding:12px 16px;border-radius:10px;font-weight:600}
  a.pdf{background:var(--jaune);color:var(--encre)}
  a.ghost{border:1px solid var(--trait);color:var(--encre)}
  .stamp{margin:22px auto 0;width:132px;height:132px;border:3px solid #C8102E;border-radius:50%;
         display:grid;place-content:center;text-align:center;color:#C8102E;transform:rotate(-8deg);
         box-shadow:inset 0 0 0 4px #fff,inset 0 0 0 5px #C8102E;opacity:.9}
  .stamp b{display:block;font-size:14px;letter-spacing:.02em}
  .stamp span{display:block;font-size:9px;letter-spacing:.12em}
  .stamp code{font-size:12px;font-weight:700}
  footer{color:var(--gris);font-size:12px;text-align:center;margin-top:22px}
  footer .power{display:block;margin-top:6px;font-weight:600;color:var(--vert)}
  .empty{text-align:center;padding:60px 20px}
</style>
</head>
<body>
<div class="wrap">
<?php if (!$facture): ?>
  <div class="card"><div class="empty">
    <h1>Attestation introuvable</h1>
    <p>Ce code ne correspond à aucune attestation de prix. Vérifiez les 8 caractères du code, ou demandez un nouveau lien à l'expéditeur.</p>
    <a class="btn pdf" href="/">Faire certifier un prix</a>
  </div></div>
  <footer><span class="power">Powered by SomeWhere App</span></footer>
<?php else:
  $d = $facture['devis']; ?>
  <div class="card">
    <div class="head">
      <img src="/logo/logo_long_vert_blanc.svg" alt="TarifColi">
      <div class="num">ATTESTATION DE PRIX · <?= $e($facture['numero']) ?> · code <?= $e($facture['code']) ?></div>
    </div>
    <div class="body">
      <div class="route">
        <div class="q">
          <span>ENLÈVEMENT</span>
          <strong><?= $e($d['depart']['nom']) ?></strong>
          <em><?= $e($d['depart']['arrondissement']) ?></em>
        </div>
        <div class="dot">›</div>
        <div class="q">
          <span>LIVRAISON</span>
          <strong><?= $e($d['arrivee']['nom']) ?></strong>
          <em><?= $e($d['arrivee']['arrondissement']) ?></em>
        </div>
      </div>

      <p style="color:var(--gris);font-size:13px;margin:0">
        <?= number_format($d['distance_km'], 2, ',', ' ') ?> km · <?= $e($d['mode_libelle']) ?> ·
        délai <?= $e($d['delai']) ?> · <?= $e($d['creneau_libelle']) ?>
      </p>

      <table>
        <?php foreach ($d['lignes'] as $l): ?>
        <tr>
          <td><?= $e($l['libelle']) ?><small><?= $e($l['detail']) ?></small></td>
          <td class="m"><?= number_format($l['montant'], 0, ',', ' ') ?></td>
        </tr>
        <?php endforeach; ?>
      </table>

      <div class="total"><span>PRIX CERTIFIÉ</span><b><?= $fcfa($facture['total']) ?></b></div>
      <p style="color:var(--gris);font-size:12px;margin:10px 0 0">
        Prix garanti <?= (int) \LivraisonCm\Invoice::VALIDITE_HEURES ?> h après émission.
        Ce document atteste d'un prix, il ne vaut pas reçu de paiement.
      </p>

<?php
      // Boutons PDF masqués. Repasser $boutonsPdf à true pour les réafficher :
      // la route /api/factures/{code}/pdf reste active.
      $boutonsPdf = false;
      if ($boutonsPdf): ?>
      <div class="actions">
        <a class="btn pdf" href="/api/factures/<?= $e($facture['code']) ?>/pdf">Télécharger le PDF</a>
        <a class="btn ghost" href="/api/factures/<?= $e($facture['code']) ?>/pdf?inline=1" target="_blank" rel="noopener">Ouvrir dans le navigateur</a>
      </div>
      <?php endif; ?>

      <div class="stamp">
        <b>TARIFCOLI</b>
        <span>DOUALA · CAMEROUN</span>
        <span style="font-weight:700;margin-top:3px">PRIX CERTIFIÉ</span>
        <span style="margin:4px 0"><?= $e(substr($facture['created_at'], 0, 10)) ?></span>
        <code><?= $e($facture['code']) ?></code>
      </div>
    </div>
  </div>
  <footer>
    Attestation émise le <?= $e($facture['created_at']) ?> · Prix en francs CFA, taxes comprises.
    <span class="power">Powered by SomeWhere App</span>
  </footer>
<?php endif; ?>
</div>
</body>
</html>
