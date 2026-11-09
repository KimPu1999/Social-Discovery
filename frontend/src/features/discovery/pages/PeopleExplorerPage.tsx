import { useEffect, useMemo, useState } from 'react'
import { ArrowRight } from 'lucide-react'
import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Button, Card, Pagination, useToast } from '../../../components/ui'
import { interpolate } from '../../../i18n'
import { DEFAULT_PEOPLE_FILTERS, filtersToSearchParams, parsePeopleFilters } from '../hooks/useDiscoveryFilters'
import type { DiscoveryProfile, FeedbackType, PeopleFilters, PeoplePerPage } from '../types'
import { useDiscoverPeople, useDiscoveryFeedback, useDiscoveryInterests } from '../hooks/useDiscovery'
import { DiscoveryFilterToolbar } from '../components/DiscoveryFilterToolbar'
import { PeopleGrid } from '../components/PeopleGrid'
import { DiscoveryEmptyState } from '../components/DiscoveryEmptyState'
import { DiscoverySkeleton } from '../components/DiscoverySkeleton'

const peopleViewStorageKey = 'social-discovery.people.view'

/** Route /people — compact, URL-addressable directory for real eligible members. */
export function PeopleExplorerPage() {
  const { t } = useTranslation()
  const [searchParams, setSearchParams] = useSearchParams()
  const [view, setView] = useState<'grid' | 'list'>(() => {
    try { return window.localStorage.getItem(peopleViewStorageKey) === 'list' ? 'list' : 'grid' } catch { return 'grid' }
  })
  const filters = useMemo(() => parsePeopleFilters(searchParams), [searchParams])
  const query = useDiscoverPeople(filters)
  const interests = useDiscoveryInterests()
  const feedback = useDiscoveryFeedback()
  const toast = useToast()
  const meta = query.data?.meta
  const totalCount = meta?.total ?? 0
  const activeFilterCount = Number(Boolean(filters.search)) + filters.interest_ids.length + Number(filters.sort !== 'relevance')

  useEffect(() => {
    try { window.localStorage.setItem(peopleViewStorageKey, view) } catch { /* storage is optional */ }
  }, [view])

  const onChange = (patch: Partial<PeopleFilters>) => {
    const next = { ...filters, ...patch }
    setSearchParams((previous) => filtersToSearchParams(previous, next), { replace: false })
  }

  useEffect(() => {
    if (meta && filters.page > meta.last_page) onChange({ page: Math.max(1, meta.last_page) })
  }, [filters.page, meta?.last_page])

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

  const range = totalCount > 0
    ? interpolate(t.discovery.people.showing, { from: Math.min(totalCount, (filters.page - 1) * filters.per_page + 1), to: Math.min(totalCount, filters.page * filters.per_page), total: totalCount })
    : interpolate(t.discovery.people.results, { count: 0 })

  return (
    <div className="discovery-page people-page">
      <header className="people-page-header">
        <div>
          <h1>{t.discovery.people.title}</h1>
          <p>{t.discovery.people.pageIntro}</p>
          <span className="sr-only">{t.discovery.people.intro}</span>
        </div>
        <Link className="ui-button ui-button-soft ui-button-sm" to="/profile">{t.discovery.people.manageInterests}<ArrowRight size={14} /></Link>
      </header>

      <DiscoveryFilterToolbar
        filters={filters}
        onChange={onChange}
        onClearAll={() => onChange({ ...DEFAULT_PEOPLE_FILTERS, per_page: filters.per_page })}
        interestNames={interestNames}
        view={view}
        onViewChange={setView}
      />

      {!query.isLoading && !query.isError ? (
        <div className="people-result-summary" aria-live="polite">
          <p>{range} <span className="people-result-total">{t.discovery.people.results.replace('{count}', String(totalCount))}</span></p>
          {activeFilterCount ? <span>{t.discovery.people.activeFilters.replace('{count}', String(activeFilterCount))}</span> : null}
        </div>
      ) : null}

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
          {meta ? (
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
          ) : null}
        </>
      )}
    </div>
  )
}
