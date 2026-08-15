// Toutes les requêtes vers le backend PHP passent par ici.
const json = async (reponse) => {
  const data = await reponse.json().catch(() => ({}))
  if (!reponse.ok) throw new Error(data.erreur || "Le service est momentanément indisponible. Réessayez.")
  return data
}

export const chercherQuartiers = (q, signal) =>
  fetch(`/api/quartiers?q=${encodeURIComponent(q)}&limit=8`, { signal }).then(json).then((d) => d.resultats)

export const estimer = (payload) =>
  fetch('/api/devis', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  }).then(json).then((d) => d.devis)

export const facturer = (payload) =>
  fetch('/api/attestations', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  }).then(json)
