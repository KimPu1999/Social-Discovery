import { useTranslation } from '../../../layouts/AppLayouts'
import { CardHeader, Skeleton } from '../../../components/ui'
import { DiscoveryProfileCard } from './DiscoveryProfileCard'
import { DiscoveryEmptyState } from './DiscoveryEmptyState'
import type { DiscoveryProfile, FeedbackType } from '../types'

type RecommendedPeopleSectionProps = {
  recommendations: DiscoveryProfile[]
  loading: boolean
  onFeedback?: (profile: DiscoveryProfile, type: FeedbackType) => void
  onUndoFeedback?: (profile: DiscoveryProfile) => void
}

/** Discover-page recommended strip: explainable shared-interest matches. */
export function RecommendedPeopleSection({ recommendations, loading, onFeedback, onUndoFeedback }: RecommendedPeopleSectionProps) {
  const { t } = useTranslation()
  return (
    <section aria-labelledby="recommended-title">
      <CardHeader title={t.discovery.recommended.sectionTitle} eyebrow={t.discovery.recommended.title} />
      {loading ? (
        <div className="discovery-grid">
          {Array.from({ length: 4 }, (_, index) => <Skeleton key={index} className="discovery-skeleton-card discovery-skeleton-line" />)}
        </div>
      ) : recommendations.length === 0 ? (
        <DiscoveryEmptyState variant="recommendedNone" />
      ) : (
        <div className="discovery-grid">
          {recommendations.map((profile) => (
            <DiscoveryProfileCard key={profile.id} profile={profile} variant="recommended" onFeedback={onFeedback} onUndoFeedback={onUndoFeedback} />
          ))}
        </div>
      )}
    </section>
  )
}
