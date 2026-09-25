// Shared labels and colour classes so every page shows categories, modes and gaps the same way.

export const CATEGORIES = [
  { key: 'core', label: 'Core' },
  { key: 'organizational', label: 'Organizational' },
  { key: 'leadership', label: 'Leadership' },
  { key: 'technical', label: 'Technical' },
]
export const categoryLabel = (key) => CATEGORIES.find((c) => c.key === key)?.label ?? key

export const categoryClass = {
  core: 'bg-brand-50 text-brand-700 dark:bg-brand-700/25 dark:text-brand-300',
  organizational: 'bg-sky-50 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
  leadership: 'bg-violet-50 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300',
  technical: 'bg-amber-50 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
}

export const LEVELS = [
  { value: 1, label: 'Basic' },
  { value: 2, label: 'Intermediate' },
  { value: 3, label: 'Advanced' },
  { value: 4, label: 'Expert' },
]
export const levelLabel = (n) => LEVELS.find((l) => l.value === Number(n))?.label ?? '—'

// DOH generic proficiency definitions (Figure 6), used when a competency has no specific descriptor.
export const GENERIC_LEVELS = {
  1: 'Uses the competency on basic tasks; applies a rudimentary understanding; partial application; still needs to build the ability to guide others.',
  2: 'Full and consistent application of the competency across different conditions; can assist others and review their work.',
  3: 'Critiques how the competency is applied; recommends improvements to work processes; transfers it to a wider range of users; gives technical assistance.',
  4: 'Provides innovative or creative solutions; gives technical assistance to users in varying situations; mentors and coaches; develops standards; consulted on strategic direction.',
}

export const MODES = [
  { key: 'formal', label: 'Formal', hint: 'Degree programs from academic institutions' },
  { key: 'non-formal', label: 'Non-formal', hint: 'In-house or external training, eLearning, seminars' },
  { key: 'informal', label: 'Informal', hint: 'Coaching, shadowing, job rotation, special projects' },
]
export const modeLabel = (key) => MODES.find((m) => m.key === key)?.label ?? key
export const modeClass = {
  formal: 'bg-violet-50 text-violet-800 ring-violet-600/20 dark:bg-violet-500/15 dark:text-violet-300',
  'non-formal': 'bg-brand-50 text-brand-700 ring-brand-600/20 dark:bg-brand-700/25 dark:text-brand-300',
  informal: 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-300',
}

// Gap = standard - actual. Positive means below standard.
export function gapClass(gap) {
  if (gap == null) return 'text-ink-600/60 dark:text-stone-500'
  if (gap >= 2) return 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/15 dark:text-red-300'
  if (gap === 1) return 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-300'
  return 'bg-brand-50 text-brand-700 ring-brand-600/20 dark:bg-brand-700/25 dark:text-brand-300'
}
export const gapText = (gap) => (gap == null ? '—' : gap > 0 ? `+${gap}` : String(gap))
export function gapMeaning(gap) {
  if (gap == null) return 'Not rated'
  if (gap > 0) return `Below standard by ${gap} level${gap > 1 ? 's' : ''}`
  if (gap === 0) return 'Met the standard'
  return `Above standard by ${-gap} level${gap < -1 ? 's' : ''}`
}

export const formatDate = (iso) =>
  iso ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(iso)) : '—'

// Training categories: who a training is for
export const TRAINING_CATEGORIES = [
  { key: 'general', label: 'General', hint: 'All positions, all areas' },
  { key: 'area', label: 'Area', hint: 'All positions, but only certain areas' },
  { key: 'position', label: 'Position', hint: 'Only certain positions (with or without areas)' },
]
export const trainingCategoryClass = {
  general: 'bg-brand-50 text-brand-700 dark:bg-brand-700/25 dark:text-brand-300',
  area: 'bg-sky-50 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
  position: 'bg-violet-50 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300',
}
