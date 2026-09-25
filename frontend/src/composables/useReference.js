import { ref, computed } from 'vue'
import { apiGet } from './useApi'

// Competencies, positions and offices are loaded once and shared by every page.
const divisions = ref([])
const levels = ref([])
const dataVersion = ref('')
const competencies = ref([])
const positions = ref([])
const offices = ref([])
const loading = ref(false)
const error = ref(null)
let pending = null

async function load(force = false) {
  if (pending && !force) return pending
  loading.value = true
  error.value = null
  pending = apiGet('reference.php')
    .then((d) => {
      divisions.value = d.divisions ?? []
      levels.value = d.levels ?? []
      dataVersion.value = d.version ?? ''
      competencies.value = d.competencies
      positions.value = d.positions
      offices.value = d.offices
    })
    .catch((err) => {
      error.value = err.message
      pending = null
    })
    .finally(() => { loading.value = false })
  return pending
}

const byId = (list) => computed(() => Object.fromEntries(list.value.map((x) => [x.id, x])))
const compById = byId(competencies)
const divisionById = byId(divisions)
const posById = byId(positions)
const officeById = byId(offices)

export function useReference() {
  load()
  const divisionCode = (office) => divisionById.value[office?.division_id]?.code ?? ''
  // Official R1MC areas and current plantilla positions: what the dropdowns offer
  const officialOffices = computed(() => offices.value.filter((o) => o.source === 'official-2026')
    .sort((a, b) => divisionCode(a).localeCompare(divisionCode(b)) || a.area.localeCompare(b.area)))
  const currentPositions = computed(() => positions.value.filter((p) => p.current !== false).sort((a, b) => a.title.localeCompare(b.title)))
  const officeLabel = (office) => (office ? [divisionCode(office), office.area].filter(Boolean).join(' · ') : '')

  // [{ division: 'FINANCE SERVICE', areas: [{ id, area }] }] - division on top, areas under it (no codes)
  const areasByDivision = (ids) => {
    const groups = []
    for (const id of ids ?? []) {
      const o = officeById.value[id]
      if (!o) continue
      const name = divisionById.value[o.division_id]?.name ?? 'Other'
      let g = groups.find((x) => x.division === name)
      if (!g) groups.push((g = { division: name, areas: [] }))
      g.areas.push({ id: o.id, area: o.area })
    }
    return groups
  }
  const levelBand = (n) => levels.value.find((l) => l.level === Number(n))
  const levelText = (n) => { const l = levelBand(n); return l ? `Level ${l.level} · SG ${l.min_sg}–${l.max_sg}` : `Level ${n}` }

  return {
    levels, levelBand, levelText, areasByDivision, dataVersion, setLevels: (v) => { levels.value = v },
    divisions, competencies, positions, offices, loading, error,
    compById, posById, officeById, divisionById, divisionCode, officeLabel, officialOffices, currentPositions,
    reload: () => load(true),
    setPositions: (v) => { positions.value = v },
    setOffices: (v) => { offices.value = v },
  }
}
