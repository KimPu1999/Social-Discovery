import { LayoutGrid, List, SlidersHorizontal } from 'lucide-react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Dropdown, IconButton } from '../../../components/ui'
import { DiscoverySearchBar } from './DiscoverySearchBar'
import { InterestFilterChips } from './InterestFilterChips'
import type { PeopleFilters, PeopleSort } from '../types'

type DiscoveryFilterToolbarProps = {
  filters: PeopleFilters
  onChange: (patch: Partial<PeopleFilters>) => void
  onClearAll: () => void
  /** Interest names by id, for active-filter chips. */
  interestNames: Map<number, string>
  view: 'grid' | 'list'
  onViewChange: (view: 'grid' | 'list') => void
}

/** Compact search + sort + interest filter + view controls for People. */
export function DiscoveryFilterToolbar({ filters, onChange, onClearAll, interestNames, view, onViewChange }: DiscoveryFilterToolbarProps) {
  const { t } = useTranslation()
  const sortOptions: readonly { value: PeopleSort; label: string }[] = [
    { value: 'relevance', label: t.discovery.sort.relevance },
    { value: 'recently_joined', label: t.discovery.sort.recently_joined },
    { value: 'username', label: t.discovery.sort.username },
  ]
  const hasFilters = Boolean(filters.search) || filters.interest_ids.length > 0 || filters.sort !== 'relevance'

  return (
    <div className="discovery-toolbar">
      <DiscoverySearchBar value={filters.search} onDebouncedChange={(value) => onChange({ search: value, page: 1 })} />
      <div className="discovery-toolbar-row">
        <Dropdown align="left" label={<span>{t.discovery.sort.label}</span>}>
          {sortOptions.map((option) => (
            <button key={option.value} className={`menu-item ${filters.sort === option.value ? 'is-active-option' : ''}`} role="menuitem" onClick={() => onChange({ sort: option.value, page: 1 })}>
              {option.label}
            </button>
          ))}
        </Dropdown>
        <InterestFilterChips withMenu selected={filters.interest_ids} onToggle={(id) => onChange({ interest_ids: filters.interest_ids.includes(id) ? filters.interest_ids.filter((x) => x !== id) : [...filters.interest_ids, id], page: 1 })} />
        <div className="discovery-view-toggle" role="group" aria-label={t.discovery.people.gridView}>
          <IconButton label={t.discovery.people.gridView} className={view === 'grid' ? 'is-active' : ''} onClick={() => onViewChange('grid')}><LayoutGrid size={17} /></IconButton>
          <IconButton label={t.discovery.people.listView} className={view === 'list' ? 'is-active' : ''} onClick={() => onViewChange('list')}><List size={17} /></IconButton>
        </div>
        {hasFilters ? <button className="text-button" onClick={onClearAll}>{t.filters.reset}</button> : <span className="discovery-toolbar-spacer" aria-hidden="true"><SlidersHorizontal size={15} /></span>}
      </div>
      {filters.interest_ids.length ? (
        <div className="ui-filter-chips">
          {filters.interest_ids.map((id) => (
            <button key={id} className="ui-chip is-selected" onClick={() => onChange({ interest_ids: filters.interest_ids.filter((x) => x !== id), page: 1 })}>
              {interestNames.get(id) ?? `#${id}`}
            </button>
          ))}
        </div>
      ) : null}
    </div>
  )
}
