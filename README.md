# livraison-cm

Estimer le prix d'une livraison entre deux quartiers de Douala, puis émettre une
**attestation de prix** : un document certifié, numéroté et vérifiable en ligne, qui
garantit le prix annoncé pendant 24 heures. Ce n'est pas une facture et cela ne vaut pas
reçu de paiement.

- **Backend** : PHP 8.1+ sans aucune dépendance Composer, base SQLite créée automatiquement.
- **Frontend** : React 18 + Vite + Tailwind + shadcn/ui.
- **PDF** : généré par un petit moteur maison (`src/Pdf/SimplePdf.php`), aucune librairie à installer.
- **Prix** : lus dans une **matrice SQLite** de 13 366 trajets (`backend/data/matrice_transport.sqlite`).
- **Données** : les 164 quartiers des 5 arrondissements de Douala (`backend/data/quartiers.json`).
- **Mention** : « Powered by SomeWhere App » en pied de l'interface, de la page publique et du PDF.

---

## Démarrage rapide

### 1. Le backend

```bash
cd livraison-cm
php -S localhost:8000 -t backend/public backend/public/index.php
```

> Le troisième argument (`backend/public/index.php`) est **obligatoire** avec le serveur
> intégré de PHP : sans lui, les fichiers du front compilé renvoient 404.

Extensions requises : `pdo_sqlite` (et `mbstring` ou `iconv`, présentes par défaut).
La base et les 164 quartiers sont créés au premier appel, dans `backend/storage/`.

L'interface est déjà compilée dans `backend/public/app` : ouvrez <http://localhost:8000>.

### 2. Le frontend (uniquement pour le modifier)

```bash
cd frontend
npm install
npm run dev     # http://localhost:5173, /api est relayé vers le port 8000
npm run build   # recompile dans backend/public/app
```

---

## Mise en production

Faites pointer le `DocumentRoot` sur `backend/public` et renvoyez tout vers `index.php`.

**Apache** — `backend/public/.htaccess` est fourni.

**Nginx**

```nginx
server {
    root /var/www/livraison-cm/backend/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

Le dossier `backend/storage/` doit être accessible en écriture par le serveur web.
Il n'est pas exposé publiquement (il est hors de `public/`).

---

## API

| Méthode | Route | Rôle |
|---|---|---|
| `GET`  | `/api/quartiers?q=bepanda&limit=8` | Autocomplétion, insensible aux accents |
| `GET`  | `/api/sante` | Vérifie que la matrice est bien rattachée (compteurs) |
| `GET`  | `/api/matrice/{a}/{b}` | La ligne brute de la matrice pour une paire |
| `GET`  | `/api/bareme` | Modes (moto, taxi partagé, taxi course, VTC) et créneaux |
| `POST` | `/api/devis` | Prix détaillé, sans rien enregistrer |
| `POST` | `/api/attestations` | Émet l'attestation, renvoie code + liens |
| `GET`  | `/api/attestations/{code}` | Relit une attestation |
| `GET`  | `/api/attestations/{code}/pdf` | PDF (`?inline=1` pour l'afficher au lieu de le télécharger) |
| `GET`  | `/a/{code}` | Page publique de vérification |

Les anciennes routes `/api/factures/...` et `/f/{code}` restent actives : ce sont des
alias, elles renvoient exactement la même chose.

Exemple :

```bash
curl -X POST http://localhost:8000/api/attestations \
  -H 'Content-Type: application/json' \
  -d '{"depart_id":12,"arrivee_id":53,"mode":"taxi","creneau":"nuit",
       "poids_kg":22,"client_nom":"Marie Ngo","client_tel":"+237 6 99 00 11 22",
       "colis":"Carton de pièces détachées"}'
```

---

## La matrice de transport

`backend/data/matrice_transport.sqlite` est un fichier SQLite autonome :

| Table | Contenu |
|---|---|
| `quartiers` | 164 lignes : nom, arrondissement, coordonnées |
| `trajets` | 13 366 lignes = toutes les paires, distance et prix des 4 modes |
| `meta` | date de génération, compteurs, barème utilisé |

Au démarrage, `Db.php` la rattache à la connexion (`ATTACH DATABASE ... AS matrice`).
`Pricing::quote()` lit alors le prix de la course avec :

```sql
SELECT * FROM matrice.trajets WHERE a_id = :a AND b_id = :b;   -- a_id < b_id
```

Le devis renvoie `source_prix` (`matrice` ou `calcul`) et `trajet_ref` : l'interface
affiche le numéro du trajet employé, et `/api/sante` confirme le rattachement.

Le calcul géométrique reste dans `Pricing::prixCourse()` mais ne sert qu'à **générer** la
matrice, et de secours si une paire manque (le devis le signale alors explicitement).

Interroger la matrice directement :

```bash
sqlite3 backend/data/matrice_transport.sqlite \
  "SELECT a_nom, b_nom, distance_route_km, prix_taxi_ramassage, prix_moto, prix_taxi, prix_vtc
     FROM trajets WHERE a_nom='Bonapriso' AND b_nom='Cité des Billes';"
-- Bonapriso|Cité des Billes|7.6|700|1250|2650|3150
```

Les quatre colonnes de prix correspondent aux quatre modes proposés dans l'interface :
`prix_taxi_ramassage` = taxi partagé (350 F le tronçon, le tarif d'un passager ordinaire),
`prix_moto`, `prix_taxi` = course privatisée, `prix_vtc`. Ce sont exactement les valeurs
du classeur Excel de la matrice.

## Régler les prix

Tout le barème tient dans la constante `BAREME` de `backend/src/Pricing.php` :
prise en charge et tarif au km par mode, tarif du tronçon de ramassage, supplément du pont
sur le Wouri, majorations horaires, paliers de poids, coefficient de détour routier.
Aucun frais de service n'est ajouté. Le client paie la course, la prestation du livreur
(`prestation` : 200 F en moto, 150 F en taxi partagé, 0 en taxi course et en VTC), la
majoration horaire éventuelle et le supplément de poids, rien d'autre.

Les boutons de téléchargement du PDF sont masqués : `AFFICHER_BOUTONS_PDF` dans
`frontend/src/components/DevisCard.jsx` et `$boutonsPdf` dans `backend/views/partage.php`.
La route `/api/factures/{code}/pdf` reste active.

```bash
# après toute modification du barème
php backend/bin/generer-matrice.php
```

La distance est calculée entre les centres des deux quartiers (formule de haversine),
majorée de 35 % pour le tracé des rues. Les trajets vers Bonabéri (Douala IV) sont
routés par le pont sur le Wouri et facturés en conséquence.

> Les prix livrés par défaut sont des **estimations** : 350 FCFA le tronçon de ramassage,
> 1 500 à 3 000 FCFA la course privatisée, 2 000 à 6 000 FCFA en VTC. À confirmer par
> vos propres relevés avant une mise en service commerciale.

---

## Arborescence

```
livraison-cm/
├── backend/
│   ├── bin/generer-matrice.php    (re)construit la matrice SQLite
│   ├── data/quartiers.json        164 quartiers (nom, arrondissement, coordonnées)
│   ├── data/matrice_transport.sqlite  13 366 trajets et leurs prix
│   ├── public/index.php           routeur unique : API, page de partage, front
│   ├── public/app/                front compilé (généré par npm run build)
│   ├── src/Db.php                 SQLite : migration + amorçage
│   ├── src/Quartiers.php          recherche et autocomplétion
│   ├── src/Pricing.php            BARÈME, lecture de la matrice, calcul de secours
│   ├── src/Invoice.php            numérotation, validité et enregistrement
│   ├── src/Text.php               accents, majuscules, encodage PDF
│   ├── src/Pdf/SimplePdf.php      moteur PDF minimal
│   ├── src/Pdf/InvoicePdf.php     mise en page de l'attestation + cachet
│   ├── storage/                   base SQLite (créée au premier lancement)
│   └── views/partage.php          page publique /a/{code}
└── frontend/
    └── src/
        ├── App.jsx                        recherche, options, résultat
        ├── components/QuartierCombobox.jsx  autocomplétion (Popover + Command)
        ├── components/DevisCard.jsx         détail du prix, attestation, cachet
        ├── components/ModeEmploi.jsx        section « comment ça marche »
        └── components/ui/                   composants shadcn/ui
```

## Vocabulaire

Le document produit est une **attestation de prix** (`ATT-2026-00001`), pas une facture :
elle certifie le montant d'une livraison à une date donnée, avec un cachet « PRIX CERTIFIÉ »
et un code de vérification. La durée de garantie se règle dans
`Invoice::VALIDITE_HEURES` (24 h par défaut).

En interne, les classes et la table s'appellent encore `Invoice` / `factures` : renommer
la base n'apportait rien et cassait les données existantes. Seul le vocabulaire visible
par l'utilisateur a changé.

## Ce qui n'est pas encore fait

- Pas d'authentification : toute personne disposant du lien voit l'attestation.
  Le code à 8 caractères (32 symboles) rend le tirage au sort peu praticable, mais ce
  n'est pas un contrôle d'accès.
- Pas de QR code sur le PDF (le lien y figure en clair).
- Pas de paiement en ligne : l'attestation indique « règlement à la livraison ».
- Les distances partent du centre du quartier, pas de l'adresse exacte.
