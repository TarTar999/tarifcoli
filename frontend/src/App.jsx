import * as React from 'react'
import { ArrowUpDown, Loader2, Package, ChevronDown } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { QuartierCombobox } from '@/components/QuartierCombobox'
import { DevisCard } from '@/components/DevisCard'
import { ModeEmploi } from '@/components/ModeEmploi'
import { estimer, facturer } from '@/lib/api'
import { cn } from '@/lib/utils'

const MODES = [
  { cle: 'moto', libelle: 'Moto' },
  { cle: 'ramassage', libelle: 'Taxi partagé' },
  { cle: 'taxi', libelle: 'Taxi course' },
  { cle: 'vtc', libelle: 'VTC' },
]

const CRENEAUX = [
  { cle: 'journee', libelle: 'Journée' },
  { cle: 'pointe', libelle: 'Heure de pointe' },
  { cle: 'nuit', libelle: 'Nuit' },
]

function Choix({ options, valeur, onChange, label }) {
  return (
    <div>
      <Label className="mb-1.5 block">{label}</Label>
      <div className="inline-flex flex-wrap rounded-lg border bg-card p-1">
        {options.map((o) => (
          <button
            key={o.cle}
            type="button"
            onClick={() => onChange(o.cle)}
            aria-pressed={valeur === o.cle}
            className={cn(
              'rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
              valeur === o.cle ? 'bg-encre text-jaune' : 'text-muted-foreground hover:bg-secondary',
            )}
          >
            {o.libelle}
          </button>
        ))}
      </div>
    </div>
  )
}

export default function App() {
  const [depart, setDepart] = React.useState(null)
  const [arrivee, setArrivee] = React.useState(null)
  const [mode, setMode] = React.useState('moto')
  const [creneau, setCreneau] = React.useState('journee')
  const [poids, setPoids] = React.useState('')
  const [details, setDetails] = React.useState(false)
  const [client, setClient] = React.useState({ client_nom: '', client_tel: '', colis: '' })

  const [devis, setDevis] = React.useState(null)
  const [facture, setFacture] = React.useState(null)
  const [erreur, setErreur] = React.useState(null)
  const [calcul, setCalcul] = React.useState(false)
  const [emission, setEmission] = React.useState(false)

  const charge = () => ({
    depart_id: depart?.id,
    arrivee_id: arrivee?.id,
    mode,
    creneau,
    poids_kg: Number(poids) || 0,
  })

  // Le prix se recalcule dès qu'un paramètre change.
  React.useEffect(() => {
    if (!depart || !arrivee || depart.id === arrivee.id) {
      setDevis(null)
      setFacture(null)
      setErreur(depart && arrivee && depart.id === arrivee.id ? 'Choisissez deux quartiers différents.' : null)
      return
    }

    let vivant = true
    setCalcul(true)
    setErreur(null)
    setFacture(null)

    estimer(charge())
      .then((d) => vivant && setDevis(d))
      .catch((e) => vivant && (setErreur(e.message), setDevis(null)))
      .finally(() => vivant && setCalcul(false))

    return () => {
      vivant = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [depart, arrivee, mode, creneau, poids])

  const permuter = () => {
    setDepart(arrivee)
    setArrivee(depart)
  }

  const emettre = () => {
    setEmission(true)
    setErreur(null)
    facturer({ ...charge(), ...client })
      .then(setFacture)
      .catch((e) => setErreur(e.message))
      .finally(() => setEmission(false))
  }

  return (
    <div className="min-h-screen">
      <header className="container flex items-center justify-between py-5">
        <div className="flex items-center gap-3">
          <a href="/" className="flex items-center">
            <img src="/logo/logo_long_vert_noir.svg" alt="TarifColi" className="h-7 sm:h-8" />
          </a>
          <span className="hidden text-xs text-muted-foreground sm:inline">
            powered by <span className="font-semibold text-vert">SomeWhere App</span>
          </span>
        </div>
        <a href="#mode-emploi" className="text-sm text-muted-foreground underline-offset-4 hover:underline">
          Comment ça marche
        </a>
      </header>

      {/* ------------------------------------------------ section 1 : recherche */}
      <main className="container max-w-4xl pb-20 pt-10 sm:pt-16">
        <div className="text-center">
          <h1 className="flex justify-center">
            <img src="/logo/logo_long_vert_noir.svg" alt="TarifColi" className="h-12 sm:h-16" />
          </h1>
          <p className="mx-auto mt-3 max-w-md text-muted-foreground">
            D'un quartier de Douala à l'autre : le prix de la livraison, certifié.
          </p>
        </div>

        <div className="mt-8 rounded-2xl border bg-card p-4 shadow-sm sm:p-5">
          <div className="grid gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
            <QuartierCombobox
              valeur={depart}
              onChange={setDepart}
              placeholder="Quartier de départ"
              teinte="vert"
              autoFocus
            />
            <Button
              variant="outline"
              size="icon"
              onClick={permuter}
              disabled={!depart && !arrivee}
              aria-label="Permuter le départ et l'arrivée"
              className="mx-auto rounded-full"
            >
              <ArrowUpDown className="sm:rotate-90" />
            </Button>
            <QuartierCombobox valeur={arrivee} onChange={setArrivee} placeholder="Quartier d'arrivée" teinte="jaune" />
          </div>

          <div className="mt-4 grid gap-4 sm:grid-cols-[1fr_auto_1fr] sm:items-end">
            <Choix options={MODES} valeur={mode} onChange={setMode} label="Mode" />
            <div>
              <Label htmlFor="poids" className="mb-1.5 block">
                Poids (kg)
              </Label>
              <Input
                id="poids"
                type="number"
                min="0"
                step="0.5"
                inputMode="decimal"
                value={poids}
                onChange={(e) => setPoids(e.target.value)}
                placeholder="0"
                className="w-24"
              />
            </div>
            <Choix options={CRENEAUX} valeur={creneau} onChange={setCreneau} label="Créneau" />
          </div>

          <button
            type="button"
            onClick={() => setDetails((v) => !v)}
            className="mt-4 flex items-center gap-1.5 text-sm text-muted-foreground underline-offset-4 hover:underline"
          >
            <Package className="h-4 w-4" />
            Ajouter l'expéditeur et le colis (facultatif)
            <ChevronDown className={cn('h-4 w-4 transition-transform', details && 'rotate-180')} />
          </button>

          {details && (
            <div className="mt-3 grid animate-monte gap-3 sm:grid-cols-3">
              <Input
                placeholder="Nom de l'expéditeur"
                value={client.client_nom}
                onChange={(e) => setClient({ ...client, client_nom: e.target.value })}
              />
              <Input
                placeholder="Téléphone"
                inputMode="tel"
                value={client.client_tel}
                onChange={(e) => setClient({ ...client, client_tel: e.target.value })}
              />
              <Input
                placeholder="Nature du colis"
                value={client.colis}
                onChange={(e) => setClient({ ...client, colis: e.target.value })}
              />
            </div>
          )}
        </div>

        {/* ----------------------------------------------------------- résultat */}
        <div className="mt-6">
          {erreur && (
            <p role="alert" className="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
              {erreur}
            </p>
          )}

          {calcul && !devis && (
            <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" /> Calcul de l'itinéraire…
            </div>
          )}

          {devis && (
            <DevisCard devis={devis} facture={facture} enCours={emission} onFacturer={emettre} />
          )}

          {!devis && !calcul && !erreur && (
            <p className="py-10 text-center text-sm text-muted-foreground">
              Choisissez les deux quartiers pour voir le prix.
            </p>
          )}
        </div>
      </main>

      {/* ------------------------------------------- section 2 : mode d'emploi */}
      <ModeEmploi />

      <footer className="container flex flex-wrap items-center justify-between gap-2 py-8 text-xs text-muted-foreground">
        <div className="flex items-center gap-2">
          <img src="/logo/logo_icon_vert.svg" alt="" className="h-5 w-5" />
          <span>TarifColi · Douala, Cameroun</span>
        </div>
        <span>
          Powered by <span className="font-semibold text-vert">SomeWhere App</span>
        </span>
        <span>Prix indicatifs en francs CFA · à recaler sur vos relevés terrain</span>
      </footer>
    </div>
  )
}
