// État d'affichage d'une mesure : fraîcheur (calculée) et qualité déclarée par la source.

export type Quality = 'good' | 'uncertain' | 'bad'
export type MeasureState = 'empty' | 'stale' | Quality
export type MeasureSample = { observedAt: string; quality?: Quality | 'stale' }

/** Tolérance sur une horloge d'équipement légèrement en avance ; au-delà, l'horodatage est jugé invalide. */
const FUTURE_TOLERANCE_MS = 5000

/**
 * « stale » (trop ancienne ou horodatage invalide) prime sur la qualité déclarée : une vieille
 * mesure n'est pas fiable, même déclarée « good ». Sinon la qualité de la source s'applique.
 */
export function measureState(sample: MeasureSample | undefined, nowMs: number, staleAfterSeconds = 30): MeasureState {
  if (!sample) return 'empty'
  const time = Date.parse(sample.observedAt)
  const fresh = Number.isFinite(time) && time - nowMs <= FUTURE_TOLERANCE_MS && nowMs - time <= staleAfterSeconds * 1000
  if (!fresh) return 'stale'
  return sample.quality === 'uncertain' || sample.quality === 'bad' ? sample.quality : 'good'
}
