import { useTranslation } from '../../../layouts/AppLayouts'
import { DiscoveryProfileCard } from './DiscoveryProfileCard'
import type { DiscoveryProfileCardProps } from './DiscoveryProfileCard'
import type { DiscoveryProfile } from '../types'

type PeopleGridProps = {
  people: DiscoveryProfile[]
  /** Multiplier hint for responsive columns (grid only). */
  variant?: 'grid' | 'list'
  onFeedback?: DiscoveryProfileCardProps['onFeedback']
  onUndoFeedback?: DiscoveryProfileCardProps['onUndoFeedback']
}

/** Responsive profile grid: 3–4 columns on desktop, 2 on tablet, 1 on mobile. */
export function PeopleGrid({ people, variant = 'grid', onFeedback, onUndoFeedback }: PeopleGridProps) {
  const { t } = useTranslation()
  if (people.length === 0) return <p className="discovery-grid-empty sr-only">{t.discovery.people.results.replace('{count}', '0')}</p>
  return (
    <div className={`discovery-grid ${variant === 'list' ? 'discovery-grid-list' : ''}`}>
      {people.map((profile) => (
        <DiscoveryProfileCard
          key={profile.id}
          profile={profile}
          variant={variant === 'list' ? 'list' : 'grid'}
          onFeedback={onFeedback}
          onUndoFeedback={onUndoFeedback}
        />
      ))}
    </div>
  )
}
