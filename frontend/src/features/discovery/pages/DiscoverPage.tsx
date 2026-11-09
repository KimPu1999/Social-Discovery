import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, Compass, Filter, Sparkles, Users } from 'lucide-react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Alert, Badge, Button, Card, useToast } from '../../../components/ui'
import { DEFAULT_PEOPLE_FILTERS } from '../hooks/useDiscoveryFilters'
import type { PeopleFilters } from '../types'
import { useDiscoverPeople, useDiscoveryFeedback, useDiscoveryOverview, useRecommendedPeople } from '../hooks/useDiscovery'
import { RecommendedPeopleSection } from '../components/RecommendedPeopleSection'
import { PeopleGrid } from '../components/PeopleGrid'
import { DiscoveryEmptyState } from '../components/DiscoveryEmptyState'
import { DiscoverySkeleton } from '../components/DiscoverySkeleton'
import { DiscoverySearchBar } from '../components/DiscoverySearchBar'
import { InterestFilterChips } from '../components/InterestFilterChips'
import type { DiscoveryProfile, FeedbackType } from '../types'

/** Route /discover — summary, recommendations, interest exploration, people grid. */
export function DiscoverPage() {
  const { t } = useTranslation()
  const toast = useToast()
  const overview = useDiscoveryOverview()
  const enabled = overview.data?.discovery_enabled ?? false

  const recommended = useRecommendedPeople(enabled)
  const [search, setSearch] = useState('')
  const [selectedInterests, setSelectedInterests] = useState<number[]>([])

  const filters = useMemo<PeopleFilters>(
    () => ({ ...DEFAULT_PEOPLE_FILTERS, per_page: 20, search, interest_ids: selectedInterests }),
    [search, selectedInterests],
  )
  const people = useDiscoverPeople(filters, enabled)
  const feedback = useDiscoveryFeedback()
  const recommendedIds = new Set((recommended.data ?? []).map((profile) => profile.id))
  const explorePeople = (people.data?.data ?? []).filter((profile) => !recommendedIds.has(profile.id))

  const onFeedback = (profile: DiscoveryProfile, type: FeedbackType) => {
    feedback.give.mutate({ targetUserId: profile.id, feedbackType: type }, {
      onSuccess: () => toast('success', type === 'hide_profile' ? t.discovery.feedback.hiddenTitle : t.discovery.feedback.notInterestedTitle, t.discovery.feedback.hiddenCopy),
      onError: () => toast('danger', t.discovery.feedback.failed),
    })
  }

  return (
    <div className="discovery-page discovery-page-redesign">
      <header className="discovery-page-header discovery-hero">
        <div>
          <div className="discovery-hero-label"><span className="feature-icon"><Compass size={16} /></span><p className="eyebrow">{t.nav.discover}</p></div>
          <h1>{t.discovery.title}</h1>
          <p>{t.discovery.intro}</p>
        </div>
        <DiscoverySearchBar value={search} onDebouncedChange={(value) => setSearch(value)} />
      </header>

      {overview.isLoading ? <DiscoverySkeleton count={4} /> : overview.isError ? (
        <Card><DiscoveryEmptyState variant="noEligible" action={<Button variant="soft" onClick={() => void overview.refetch()}>{t.discovery.error.retry}</Button>} /></Card>
      ) : overview.data ? (
        <>
          <section aria-label={t.discovery.title} className="discovery-summary">
            <div className="discovery-summary-top"><div><Badge tone={enabled ? 'success' : 'warning'}>{enabled ? t.discovery.badgeOn : t.discovery.badgeOff}</Badge>{enabled ? <p>{t.discovery.recommended.copy}</p> : null}</div><Link className="ui-button ui-button-soft ui-button-sm" to="/settings/discovery">{t.discovery.openSettings}<ArrowRight size={14} /></Link></div>
            <dl className="discovery-summary-counts">
              <div><dt><Users size={15} aria-hidden="true" /> {t.discovery.overview.discoverable}</dt><dd>{overview.data.counts.discoverable_people}</dd></div>
              <div><dt><Sparkles size={15} aria-hidden="true" /> {t.discovery.overview.recommended}</dt><dd>{overview.data.counts.recommended_people}</dd></div>
              <div><dt>{t.discovery.overview.mine}</dt><dd>{overview.data.counts.my_interests}</dd></div>
              <div><dt>{t.discovery.overview.hidden}</dt><dd>{overview.data.counts.hidden_profiles}</dd></div>
            </dl>
          </section>

          {!enabled && <Alert tone="warning" title={t.discovery.empty.disabled}>{t.discovery.empty.disabledCopy}</Alert>}

          {enabled && (
            <>
              <RecommendedPeopleSection
                recommendations={recommended.data ?? []}
                loading={recommended.isLoading}
                onFeedback={onFeedback}
                onUndoFeedback={undefined}
              />

              <section aria-labelledby="interests-title">
                <div className="discovery-section-heading"><div><p className="eyebrow">{t.discovery.explore.copy}</p><h2>{t.discovery.explore.title}</h2></div><span className="discovery-section-icon"><Filter size={16} /></span></div>
                <InterestFilterChips
                  selected={selectedInterests}
                  onToggle={(id) => setSelectedInterests((value) => value.includes(id) ? value.filter((x) => x !== id) : [...value, id])}
                  showCounts
                />
              </section>

              <section aria-labelledby="explore-people-title">
                <div className="discovery-section-heading"><div><p className="eyebrow">{t.discovery.people.intro}</p><h2>{t.discovery.explore.byInterests}</h2></div><span className="discovery-section-icon"><Users size={16} /></span></div>
                {people.isLoading ? <DiscoverySkeleton /> : people.isError ? (
                  <Card><DiscoveryEmptyState variant="noResults" action={<Button variant="soft" onClick={() => void people.refetch()}>{t.discovery.error.retry}</Button>} /></Card>
                ) : explorePeople.length === 0 ? (
                  selectedInterests.length
                    ? <DiscoveryEmptyState variant="noMatchingInterests" action={<Button variant="soft" onClick={() => setSelectedInterests([])}>{t.filters.clear}</Button>} />
                    : search
                      ? <DiscoveryEmptyState variant="noResults" action={<Button variant="soft" onClick={() => setSearch('')}>{t.filters.clear}</Button>} />
                      : <DiscoveryEmptyState variant="noEligible" />
                ) : (
                  <PeopleGrid people={explorePeople} onFeedback={onFeedback} />
                )}
              </section>
            </>
          )}
        </>
      ) : null}
    </div>
  )
}
