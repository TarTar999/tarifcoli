import * as React from 'react'
import { Download, Link2, Check, AlertTriangle, ArrowRight, FileText, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { fcfa, km } from '@/lib/utils'

function Cachet({ code, date }) {
  return (
    <div
      aria-hidden="true"
      className="grid h-[132px] w-[132px] shrink-0 animate-tampon place-content-center rounded-full border-[3px] border-cachet text-center text-cachet shadow-[inset_0_0_0_4px_#fff,inset_0_0_0_5px_#C8102E]"
    >
      <span className="font-display text-[15px] font-extrabold tracking-tight">LIVRAISON-CM</span>
      <span className="text-[9px] tracking-[0.16em]">DOUALA · CAMEROUN</span>
      <span className="my-1 block h-px bg-cachet/70" />
      <span className="text-[10px] font-bold tracking-[0.12em]">PRIX CERTIFIÉ</span>
      <span className="font-mono text-[10px]">{date}</span>
      <span className="font-mono text-[12px] font-semibold">{code}</span>
    </div>
  )
}

// Passer à true pour réafficher les boutons de téléchargement du PDF.
const AFFICHER_BOUTONS_PDF = false

export function DevisCard({ devis, facture, enCours, onFacturer }) {
  const [copie, setCopie] = React.useState(false)

  const copier = async () => {
    try {
      await navigator.clipboard.writeText(facture.lien_partage)
      setCopie(true)
      setTimeout(() => setCopie(false), 2200)
    } catch {
      window.prompt("Copiez le lien de l'attestation :", facture.lien_partage)
    }
  }

  return (
    <div className="animate-monte overflow-hidden rounded-xl border bg-card shadow-sm">
      {/* trajet */}
      <div className="flex flex-wrap items-center gap-x-3 gap-y-2 border-b bg-encre px-5 py-4 text-white">
        <span className="font-display text-lg font-semibold">{devis.depart.nom}</span>
        <ArrowRight className="h-4 w-4 text-jaune" />
        <span className="font-display text-lg font-semibold">{devis.arrivee.nom}</span>
        <span className="ml-auto font-mono text-sm text-white/70">{km(devis.distance_km)}</span>
      </div>

      <div className="flex flex-wrap gap-2 px-5 pt-4">
        <Badge variant="jaune">{devis.mode_libelle}</Badge>
        <Badge variant="secondary">Délai {devis.delai}</Badge>
        <Badge variant="secondary">{devis.creneau_libelle}</Badge>
        {devis.traverse_pont && <Badge variant="vert">Pont du Wouri</Badge>}
      </div>

      {/* lignes de facturation */}
      <dl className="px-5 py-4">
        {devis.lignes.map((ligne, i) => (
          <div key={i} className="flex items-baseline justify-between gap-4 border-b border-dashed py-2.5 last:border-0">
            <dt className="min-w-0">
              <span className="block text-sm font-medium">{ligne.libelle}</span>
              <span className="block text-xs text-muted-foreground">{ligne.detail}</span>
            </dt>
            <dd className="shrink-0 font-mono text-sm">{new Intl.NumberFormat('fr-FR').format(ligne.montant)}</dd>
          </div>
        ))}
      </dl>

      <div className="mx-5 mb-5 flex items-center justify-between rounded-lg bg-encre px-5 py-4 text-white">
        <span className="text-[11px] font-semibold uppercase tracking-[0.16em] text-jaune">Total à payer</span>
        <span className="font-mono text-2xl font-semibold">{fcfa(devis.total)}</span>
      </div>

      {devis.alertes?.length > 0 && (
        <ul className="mx-5 mb-5 space-y-1.5 rounded-lg bg-secondary p-4">
          {devis.alertes.map((a, i) => (
            <li key={i} className="flex gap-2 text-xs text-muted-foreground">
              <AlertTriangle className="mt-px h-3.5 w-3.5 shrink-0 text-vert" />
              {a}
            </li>
          ))}
        </ul>
      )}

      <Separator />

      {/* facturation */}
      {!facture ? (
        <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
          <p className="text-sm text-muted-foreground">
            Le prix vous convient ? Faites-le certifier : il sera garanti 24 h.
          </p>
          <Button variant="jaune" size="lg" onClick={onFacturer} disabled={enCours}>
            {enCours ? <Loader2 className="animate-spin" /> : <FileText />}
            {enCours ? 'Validation…' : 'Valider les données'}
          </Button>
        </div>
      ) : (
        <div className="flex flex-col gap-5 px-5 py-5 sm:flex-row sm:items-center">
          <Cachet code={facture.facture.code} date={facture.facture.created_at.slice(0, 10)} />

          <div className="min-w-0 flex-1 space-y-3">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                Prix certifié · attestation
              </p>
              <p className="font-mono text-lg">{facture.facture.numero}</p>
              <p className="break-all font-mono text-xs text-vert">{facture.lien_partage}</p>
            </div>

            <div className="flex flex-wrap gap-2">
              {AFFICHER_BOUTONS_PDF && (
                <Button variant="jaune" asChild>
                  <a href={facture.lien_pdf} download>
                    <Download /> Télécharger l'attestation
                  </a>
                </Button>
              )}
              <Button variant={AFFICHER_BOUTONS_PDF ? 'outline' : 'jaune'} onClick={copier}>
                {copie ? <Check className="text-vert" /> : <Link2 />}
                {copie ? 'Lien copié' : 'Copier le lien'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
