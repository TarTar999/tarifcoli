import * as React from 'react'
import { ChevronsUpDown, Check, MapPin, Loader2 } from 'lucide-react'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { chercherQuartiers } from '@/lib/api'
import { cn } from '@/lib/utils'

/**
 * Choix d'un quartier parmi les 164 de Douala.
 * La recherche se fait côté serveur : « bepanda » trouve « Bépanda Omnisports ».
 */
export function QuartierCombobox({ valeur, onChange, placeholder, teinte = 'vert', autoFocus = false }) {
  const [ouvert, setOuvert] = React.useState(false)
  const [saisie, setSaisie] = React.useState('')
  const [resultats, setResultats] = React.useState([])
  const [chargement, setChargement] = React.useState(false)

  React.useEffect(() => {
    if (!ouvert) return
    const controleur = new AbortController()
    const minuteur = setTimeout(() => {
      setChargement(true)
      chercherQuartiers(saisie, controleur.signal)
        .then(setResultats)
        .catch(() => {})
        .finally(() => setChargement(false))
    }, 160)

    return () => {
      clearTimeout(minuteur)
      controleur.abort()
    }
  }, [saisie, ouvert])

  const barre = teinte === 'vert' ? 'bg-vert' : 'bg-jaune'

  return (
    <Popover open={ouvert} onOpenChange={setOuvert}>
      <PopoverTrigger asChild>
        <button
          type="button"
          role="combobox"
          aria-expanded={ouvert}
          autoFocus={autoFocus}
          className="group flex w-full items-center gap-3 rounded-lg border border-input bg-card px-3 py-3 text-left transition-colors hover:border-vert/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          <span className={cn('h-9 w-1.5 shrink-0 rounded-full', barre)} />
          <span className="min-w-0 flex-1">
            {valeur ? (
              <>
                <span className="block truncate font-display text-[17px] font-semibold leading-tight">{valeur.nom}</span>
                <span className="block truncate text-xs text-muted-foreground">{valeur.arrondissement}</span>
              </>
            ) : (
              <span className="block truncate text-[15px] text-muted-foreground">{placeholder}</span>
            )}
          </span>
          <ChevronsUpDown className="h-4 w-4 shrink-0 opacity-40 group-hover:opacity-70" />
        </button>
      </PopoverTrigger>

      <PopoverContent
        className="w-[--radix-popover-trigger-width] min-w-[280px]"
        onOpenAutoFocus={(e) => e.preventDefault()}
      >
        <Command shouldFilter={false}>
          <CommandInput placeholder="Tapez les premières lettres…" value={saisie} onValueChange={setSaisie} autoFocus />
          <CommandList>
            {chargement && resultats.length === 0 ? (
              <div className="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" /> Recherche…
              </div>
            ) : (
              <CommandEmpty>Aucun quartier ne correspond. Essayez une autre orthographe.</CommandEmpty>
            )}
            <CommandGroup heading={saisie ? 'Résultats' : 'Quartiers desservis'}>
              {resultats.map((q) => (
                <CommandItem
                  key={q.id}
                  value={String(q.id)}
                  onSelect={() => {
                    onChange(q)
                    setOuvert(false)
                    setSaisie('')
                  }}
                >
                  <MapPin className="h-4 w-4 shrink-0 text-vert" />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium">{q.nom}</span>
                    <span className="block truncate text-xs text-muted-foreground">{q.arrondissement}</span>
                  </span>
                  {valeur?.id === q.id && <Check className="h-4 w-4 text-vert" />}
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}
