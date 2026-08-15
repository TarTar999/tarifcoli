import * as React from 'react'
import { Search, SlidersHorizontal, FileText, Share2 } from 'lucide-react'

const etapes = [
  {
    icone: Search,
    titre: 'Indiquez les deux quartiers',
    texte:
      "Tapez les premières lettres du quartier d'enlèvement, puis du quartier de livraison. Les accents sont optionnels : « bepanda » suffit pour trouver Bépanda Omnisports. Les 164 quartiers des cinq arrondissements de Douala sont couverts.",
  },
  {
    icone: SlidersHorizontal,
    titre: 'Réglez le mode et le créneau',
    texte:
      "Moto pour un pli ou un petit colis ; taxi partagé (ramassage) si le colis voyage avec le coursier au tarif passager de 350 F le tronçon ; taxi course quand il faut privatiser le véhicule ; VTC pour une course dédiée et suivie. Le créneau applique la majoration d'heure de pointe ou de nuit, et le poids déclaré ajoute le supplément correspondant.",
  },
  {
    icone: FileText,
    titre: 'Faites certifier le prix',
    texte:
      "Le prix se met à jour à chaque changement. Quand il vous convient, « Valider les données » émet une attestation de prix : un numéro, un code de vérification à 8 caractères, le cachet TarifColi, et un prix garanti 24 heures.",
  },
  {
    icone: Share2,
    titre: 'Partagez le lien',
    texte:
      "Le lien de partage ouvre l'attestation dans le navigateur du client ou du coursier, qui peut ainsi vérifier le prix annoncé sans créer de compte. Le PDF reste généré côté serveur et se rebranche en changeant un seul indicateur.",
  },
]

export function ModeEmploi() {
  return (
    <section id="mode-emploi" className="border-t bg-card/60 py-16 sm:py-24">
      <div className="container max-w-4xl">
        <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-vert">Mode d'emploi</p>
        <h2 className="mt-3 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">
          Un prix de livraison certifié en quatre gestes
        </h2>
        <p className="mt-3 max-w-2xl text-muted-foreground">
          L'outil sert aux boutiques, aux coursiers et aux particuliers qui veulent un prix clair, opposable et
          vérifiable avant que le colis ne parte.
        </p>

        <ol className="mt-10 space-y-4">
          {etapes.map(({ icone: Icone, titre, texte }, i) => (
            <li key={titre} className="flex gap-5 rounded-xl border bg-card p-5">
              <div className="flex flex-col items-center gap-2">
                <span className="grid h-10 w-10 shrink-0 place-content-center rounded-full bg-encre font-mono text-sm font-semibold text-jaune">
                  {i + 1}
                </span>
                {i < etapes.length - 1 && <span className="w-px flex-1 bg-border" />}
              </div>
              <div className="min-w-0">
                <h3 className="flex items-center gap-2 font-display text-lg font-semibold">
                  <Icone className="h-4 w-4 text-vert" />
                  {titre}
                </h3>
                <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">{texte}</p>
              </div>
            </li>
          ))}
        </ol>

        <div className="mt-10 rounded-xl border border-dashed p-5 text-sm text-muted-foreground">
          <p className="font-semibold text-foreground">Comment le prix est calculé</p>
          <p className="mt-2 leading-relaxed">
            Le prix de la course n'est pas recalculé à chaque requête : il est lu dans une matrice SQLite qui contient
            les 13 366 paires de quartiers possibles, avec pour chacune la distance et le tarif des quatre modes. La
            fiche de résultat indique le numéro du trajet utilisé. S'ajoutent ensuite la seule majoration horaire et,
            s'il y a lieu, le supplément de poids : aucun frais de service n'est prélevé.
            Le barème se modifie dans <code className="font-mono text-xs">Pricing.php</code>, puis la matrice se
            régénère avec <code className="font-mono text-xs">php backend/bin/generer-matrice.php</code>.
          </p>
        </div>
      </div>
    </section>
  )
}
