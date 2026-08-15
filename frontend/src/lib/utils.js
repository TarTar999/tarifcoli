import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export const fcfa = (n) => new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'

export const km = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(n) + ' km'
