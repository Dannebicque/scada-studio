import { computed } from 'vue'

/** Display local time explicitly; never infer a timezone from an ISO string without an offset. */
export function useLocalizedDateTime() {
  const { locale } = useAppLocale()
  const language = computed(() => locale.value === 'fr' ? 'fr-FR' : 'en-GB')
  const timeZone = ref('UTC')
  onMounted(() => { timeZone.value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC' })
  function formatDateTime(value: string | number | Date | undefined | null): string {
    if (value === null || value === undefined || value === '') return '—'
    if (typeof value === 'string' && !/(Z|[+-]\d{2}:?\d{2})$/i.test(value)) return '—'
    const date = new Date(value)
    if (!Number.isFinite(date.getTime())) return '—'
    return new Intl.DateTimeFormat(language.value, {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit', second: '2-digit',
      hourCycle: 'h23', timeZone: timeZone.value,
    }).format(date)
  }
  function formatTime(value: number): string {
    return new Intl.DateTimeFormat(language.value, {
      hour: '2-digit', minute: '2-digit', second: '2-digit',
      hourCycle: 'h23', timeZone: timeZone.value,
    }).format(value)
  }
  return { formatDateTime, formatTime, timeZone }
}
