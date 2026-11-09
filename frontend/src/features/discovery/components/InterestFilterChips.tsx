import { EyeOff, SearchX } from 'lucide-react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Dropdown } from '../../../components/ui'
import { useDiscoveryInterests } from '../hooks/useDiscovery'
import type { DiscoveryInterestOption } from '../types'

/**
 * "Explore by interest" chips / filter row. Interests come from
 * GET /discovery/interests (real counts when loaded); selection is
 * ANY-of, mirroring the backend `interest_ids` filter.
 */
export function InterestFilterChips({ selected, onToggle, disabled = false, showCounts = false, withMenu = false }: {
  selected: number[]
  onToggle: (interestId: number) => void
  disabled?: boolean
  showCounts?: boolean
  withMenu?: boolean
}) {
  const { t } = useTranslation()
  const interests = useDiscoveryInterests(!disabled)

  if (withMenu) {
    const options = interests.data?.flatMap((group) => group.interests) ?? []
    return (
      <Dropdown align="left" label={<span>{t.discovery.people.interestsFilter}</span>}>
        <button className="menu-item" onClick={() => selected.forEach((id) => onToggle(id))} role="menuitem">
          <SearchX size={14} aria-hidden="true" />
          {t.filters.clear}
        </button>
        {options.map((interest) => (
          <button key={interest.id} className={`menu-item ${selected.includes(interest.id) ? 'is-active-option' : ''}`} role="menuitem" onClick={() => onToggle(interest.id)}>
            {interest.name}
            {showCounts ? <span className="discovery-chip-count">{interest.people_count}</span> : null}
          </button>
        ))}
      </Dropdown>
    )
  }

  return (
    <div className="discovery-interest-row" role="group" aria-label={t.discovery.people.interestsFilter}>
      {(interests.data ?? []).map((group) => group.interests).flat().map((interest) => (
        <Chip key={interest.id} interest={interest} selected={selected.includes(interest.id)} onToggle={onToggle} disabled={disabled} showCounts={showCounts} />
      ))}
      {interests.isLoading ? Array.from({ length: 8 }, (_, index) => <span key={index} className="ui-chip ui-skeleton" aria-hidden="true" />) : null}
    </div>
  )
}

function Chip({ interest, selected, onToggle, disabled, showCounts }: {
  interest: DiscoveryInterestOption
  selected: boolean
  onToggle: (id: number) => void
  disabled?: boolean
  showCounts?: boolean
}) {
  const label = showCounts ? `${interest.name} · ${interest.people_count}` : interest.name
  return (
    <button type="button" className={`ui-chip ${selected ? 'is-selected' : ''}`} aria-pressed={selected} disabled={disabled} onClick={() => onToggle(interest.id)}>
      {label}
    </button>
  )
}

/** Legend icon for hidden-profile context (kept for future feedback summary UI). */
export function HiddenProfilesHint({ count }: { count: number }) {
  if (!count) return null
  return <span className="discovery-hidden-hint"><EyeOff size={14} aria-hidden="true" />{count}</span>
}
