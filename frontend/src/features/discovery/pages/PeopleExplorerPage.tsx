import { useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Button, Card } from '../../../components/ui'
import { DEFAULT_PEOPLE_FILTERS, filtersToSearchParams, parsePeopleFilters } from '../hooks/useDiscoveryFilters'
import type { PeopleFilters } from '../types'
import { useDiscoverPeople, useDiscoveryFeedback, useDiscoveryInterests } from '../hooks/useDiscovery'
import { DiscoveryFilterToolbar } from '../components/DiscoveryFilterToolbar'
import { PeopleGrid } from '../components/PeopleGrid'
import { DiscoveryEmptyState } from '../components/DiscoveryEmptyState'
import { DiscoverySkeleton } from '../components/DiscoverySkeleton'
import { Pagination } from '../../../components/ui'
import { interpolate } from '../../../i18n'
import { useToast } from '../../../components/ui'
import type { DiscoveryProfile, FeedbackType, PeoplePerPage } from '../types'

/** Route /people — full exploration workspace with URL-synchronized filters. */
export function PeopleExplorerPage() {
  const { t } = useTranslation()
  const [searchParams, setSearchParams] = useSearchParams()
  const filters = useMemo(() => parsePeopleFilters(searchParams), [searchParams])
  const [view, setView] = useState<'grid' | 'list'>('grid')

  const query = useDiscoverPeople(filters)
  const interests = useDiscoveryInterests()
  const feedback = useDiscoveryFeedback()
  const toast = useToast()

  const onChange = (patch: Partial<PeopleFilters>) => {
    const next = { ...filters, ...patch }
    setSearchParams((previous) => filtersToSearchParams(previous, next), { replace: false })
  }

  const onFeedback = (profile: DiscoveryProfile, type: FeedbackType) => {
    feedback.give.mutate({ targetUserId: profile.id, feedbackType: type }, {
      onSuccess: () => toast('success', type === 'hide_profile' ? t.discovery.feedback.hiddenTitle : t.discovery.feedback.notInterestedTitle, t.discovery.feedback.hiddenCopy),
      onError: () => toast('danger', t.discovery.feedback.failed),
    })
  }

  const interestNames = useMemo(() => {
    const map = new Map<number, string>()
    for (const group of interests.data ?? []) for (const interest of group.interests) map.set(interest.id, interest.name)
    return map
  }, [interests.data])

  const meta = query.data?.meta
  const totalCount = meta?.total ?? 0

  return (
    <div className="discovery-page">
      <header className="discovery-page-header">
        <div>
          <p className="eyebrow">{t.discovery.people.title}</p>
          <h1>{t.discovery.people.title}</h1>
          <p>{t.discovery.people.intro}</p>
        </div>
      </header>

      <DiscoveryFilterToolbar filters={filters} onChange={onChange} onClearAll={() => onChange({ ...DEFAULT_PEOPLE_FILTERS, per_page: filters.per_page })} interestNames={interestNames} view={view} onViewChange={setView} />

      <p className="discovery-result-count" aria-live="polite">
        {interests.isError || query.isError
          ? null
          : interpolate(t.discovery.people.results, { count: totalCount })}
      </p>

      {query.isLoading ? (
        <DiscoverySkeleton count={filters.per_page} />
      ) : query.isError ? (
        <Card className="discovery-error-card" role="alert">
          <h2>{t.discovery.error.title}</h2>
          <p>{t.discovery.error.copy}</p>
          <Button variant="soft" onClick={() => void query.refetch()}>{t.discovery.error.retry}</Button>
        </Card>
      ) : totalCount === 0 ? (
        filters.interest_ids.length ? (
          <DiscoveryEmptyState variant="noMatchingInterests" action={<Button variant="soft" onClick={() => onChange({ interest_ids: [], page: 1 })}>{t.filters.clear}</Button>} />
        ) : filters.search ? (
          <DiscoveryEmptyState variant="noResults" action={<Button variant="soft" onClick={() => onChange({ search: '', page: 1 })}>{t.filters.clear}</Button>} />
        ) : (
          <DiscoveryEmptyState variant="noEligible" />
        )
      ) : (
        <>
          <PeopleGrid people={query.data?.data ?? []} variant={view} onFeedback={onFeedback} />
          {meta && (
            <Pagination
              page={filters.page}
              pageSize={filters.per_page as PeoplePerPage}
              total={totalCount}
              onPage={(page) => onChange({ page })}
              onPageSize={(size) => onChange({ per_page: size as PeoplePerPage, page: 1 })}
              pageSizeOptions={[10, 20, 50]}
              rowsPerPageLabel={t.discovery.pagination.rowsPerPage}
              rangeLabel={interpolate(t.discovery.pagination.range, { from: Math.min(totalCount, (filters.page - 1) * filters.per_page + 1), to: Math.min(totalCount, filters.page * filters.per_page), total: totalCount })}
            />
          )}
        </>
      )}
    </div>
  )
}
