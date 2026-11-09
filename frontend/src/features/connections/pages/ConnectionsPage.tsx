import { useDeferredValue, useState } from 'react'
import { useOutletContext, useSearchParams } from 'react-router-dom'
import { Search, UserMinus, Undo2, UsersRound, Inbox, Send, ArrowDownAZ } from 'lucide-react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Avatar, Badge, Button, Card, CardHeader, EmptyState, ErrorState, Pagination, Skeleton, Tabs, useToast, ConfirmDialog } from '../../../components/ui'
import { formatLocaleDate, interpolate } from '../../../i18n'
import {
  useBlockedUsers,
  useFollowers,
  useFollowing,
  useIncomingRequests,
  useMyConnections,
  useConnectionSuggestions,
  useOutgoingRequests,
  useRemoveConnection,
  useBlockActions,
} from '../hooks/useConnections'
import { ConnectionStatusBadge } from '../components/ConnectionStatusBadge'
import { ConnectionRequestButton } from '../components/ConnectionRequestButton'
import { FollowButton } from '../components/FollowButton'
import { SocialUserCard } from '../components/SocialUserCard'
import { IncomingRequestCard, OutgoingRequestCard } from '../components/RequestCards'
import { DiscoveryProfileCard } from '../../discovery/components/DiscoveryProfileCard'
import { CONNECTION_TABS, type ConnectionSort, type ConnectionTab, type SocialListParams } from '../types'

const PER_PAGE_OPTIONS = [5, 10, 20, 50] as const

function isConnectionTab(value: string): value is ConnectionTab {
  return (CONNECTION_TABS as readonly string[]).includes(value)
}

/** Shared per-tab list state so every tab keeps its own page/page size. */
function useListState(tab: ConnectionTab) {
  const [urlParams, setUrlParams] = useSearchParams()
  const page = Math.max(Number(urlParams.get('page') || 1), 1)
  const requestedPerPage = Number(urlParams.get('per_page') || 10)
  const perPage = [5, 10, 20, 50].includes(requestedPerPage) ? requestedPerPage : 10
  const search = urlParams.get('q') ?? ''
  const sort: ConnectionSort = urlParams.get('sort') === 'name' ? 'name' : 'newest'
  const deferredSearch = useDeferredValue(search)
  const params: SocialListParams = {
    page,
    per_page: perPage,
    sort,
    ...(deferredSearch.trim() ? { search: deferredSearch.trim() } : {}),
  }

  const sync = (updates: { page?: number; per_page?: number; q?: string; sort?: ConnectionSort }) => {
    setUrlParams((current) => {
      const next = new URLSearchParams(current)
      if (updates.page && updates.page > 1) next.set('page', String(updates.page)); else next.delete('page')
      if (updates.per_page && updates.per_page !== 10) next.set('per_page', String(updates.per_page)); else next.delete('per_page')
      if (updates.q?.trim()) next.set('q', updates.q.trim()); else next.delete('q')
      if (updates.sort && updates.sort !== 'newest') next.set('sort', updates.sort); else next.delete('sort')
      if (tab !== 'connections') next.set('tab', tab); else next.delete('tab')
      return next
    }, { replace: true })
  }

  const onSearch = (value: string) => { sync({ page: 1, per_page: perPage, q: value, sort }) }
  const onSort = (value: ConnectionSort) => { sync({ page: 1, per_page: perPage, q: search, sort: value }) }
  const onPaging = (nextPage: number, nextPerPage: number) => {
    sync({ page: nextPage, per_page: nextPerPage, q: search, sort })
  }

  return { page, perPage, search, sort, params, onSearch, onSort, onPaging }
}

/**
 * /connections workspace. The active tab lives in the `tab` URL param so
 * links (like connection-request notifications) land directly on the
 * right list; every tab shares the search + pagination chrome.
 */
export function ConnectionsPage() {
  const { t } = useTranslation()
  const [searchParams, setSearchParams] = useSearchParams()
  const rawTab = searchParams.get('tab') ?? 'connections'
  const tab: ConnectionTab = isConnectionTab(rawTab) ? rawTab : 'connections'
  const incoming = useIncomingRequests({ page: 1, per_page: 1 })
  const overviewConnections = useMyConnections({ page: 1, per_page: 1 })
  const overviewOutgoing = useOutgoingRequests({ page: 1, per_page: 1 })
  const pendingCount = incoming.data?.meta.total ?? 0
  const totalConnections = overviewConnections.data?.meta.total ?? 0
  const sentCount = overviewOutgoing.data?.meta.total ?? 0

  const changeTab = (next: string) => setSearchParams((current) => {
    const nextParams = new URLSearchParams(current)
    if (next === 'connections') nextParams.delete('tab'); else nextParams.set('tab', next)
    // Keep search/sort/page size useful across tabs, but never carry a page
    // number that may be beyond the next tab's total.
    nextParams.delete('page')
    return nextParams
  })

  return (
    <div className="page connections-page" data-testid="connections-page">
      <CardHeader
        eyebrow={t.nav.social}
        title={t.connections.title}
        action={pendingCount > 0 ? <Badge tone="primary">{interpolate(t.connections.pendingCount, { count: pendingCount })}</Badge> : null}
      />
      <p className="page-copy">{t.connections.intro}</p>

      <section className="connections-overview" aria-label={t.connections.overviewLabel}>
        <div className="connections-stat"><UsersRound size={17} aria-hidden="true" /><span>{t.connections.stats.connections}</span><strong>{totalConnections}</strong></div>
        <div className="connections-stat"><Inbox size={17} aria-hidden="true" /><span>{t.connections.stats.incoming}</span><strong>{pendingCount}</strong></div>
        <div className="connections-stat"><Send size={17} aria-hidden="true" /><span>{t.connections.stats.outgoing}</span><strong>{sentCount}</strong></div>
      </section>

      <Tabs
        label={t.connections.tabsLabel}
        items={CONNECTION_TABS.map((id) => ({ id, label: t.connections.tabs[id] }))}
        active={tab}
        onChange={changeTab}
      />

      {tab === 'incoming' ? <IncomingRequestsTab /> : null}
      {tab === 'outgoing' ? <OutgoingRequestsTab /> : null}
      {tab === 'suggestions' ? <SuggestionsTab /> : null}
      {tab === 'connections' ? <MyConnectionsTab /> : null}
      {tab === 'followers' ? <EdgesTab mode="followers" /> : null}
      {tab === 'following' ? <EdgesTab mode="following" /> : null}
      {tab === 'blocked' ? <BlockedTab /> : null}
    </div>
  )
}

/** Shared chrome (toolbar, states, pagination) around each tab's list. */
function ListFrame({
  meta, loading, error, search, sort, onSearch, onSort, onRetry, onPaging, children, testId, grid = false,
}: {
  meta?: { current_page: number; last_page: number; per_page: number; total: number }
  loading: boolean
  error: boolean
  search: string
  onSearch: (value: string) => void
  sort: ConnectionSort
  onSort: (value: ConnectionSort) => void
  onRetry: () => void
  onPaging: (page: number, perPage: number) => void
  children: React.ReactNode
  testId: string
  grid?: boolean
}) {
  const { t } = useTranslation()

  return (
    <div className="connections-list" data-testid={testId}>
      <div className="connections-list-top">
        <form className="connections-toolbar" onSubmit={(event) => { event.preventDefault() }}>
          <Search size={16} aria-hidden="true" />
          <label className="sr-only" htmlFor={`${testId}-search`}>{t.connections.searchLabel}</label>
          <input
            id={`${testId}-search`}
            type="search"
            value={search}
            placeholder={t.connections.searchPlaceholder}
            onChange={(event) => { onSearch(event.target.value) }}
          />
        </form>
        <div className="connections-filter-row">
          <label className="connections-sort">
            <ArrowDownAZ size={15} aria-hidden="true" />
            <span className="sr-only">{t.connections.sortLabel}</span>
            <select value={sort} onChange={(event) => onSort(event.target.value as ConnectionSort)}>
              <option value="newest">{t.connections.sortNewest}</option>
              <option value="name">{t.connections.sortName}</option>
            </select>
          </label>
          {meta && meta.total > 0 ? <p className="connections-count">{interpolate(t.connections.resultCount, { count: meta.total })}</p> : null}
        </div>
      </div>

      {loading ? <ListSkeleton /> : null}
      {error ? <ErrorState title={t.connections.errors.loadFailed} description={t.connections.errors.loadFailedCopy} action={<Button variant="soft" size="sm" onClick={onRetry}>{t.connections.errors.retry}</Button>} /> : null}
      {!loading && !error && meta?.total === 0 ? <EmptyState icon={<UserMinus size={20} aria-hidden="true" />} title={t.connections.empty.title} description={t.connections.empty.copy} /> : null}
      {!loading && !error ? <div className={grid ? 'connections-suggestion-grid' : undefined}>{children}</div> : null}

      {meta && meta.last_page > 1 ? (
        <Pagination
          page={meta.current_page}
          pageSize={meta.per_page}
          total={meta.total}
          onPage={(nextPage) => onPaging(nextPage, meta.per_page)}
          onPageSize={(size) => onPaging(1, size)}
          pageSizeOptions={PER_PAGE_OPTIONS}
          rowsPerPageLabel={t.connections.pagination.rowsPerPage}
          rangeLabel={interpolate(t.connections.pagination.range, { from: Math.min(meta.total, (meta.current_page - 1) * meta.per_page + 1), to: Math.min(meta.total, meta.current_page * meta.per_page), total: meta.total })}
        />
      ) : null}
    </div>
  )
}

function ListSkeleton() {
  return (
    <div className="connections-list-rows">
      <Skeleton className="social-row-skeleton" />
      <Skeleton className="social-row-skeleton" />
      <Skeleton className="social-row-skeleton" />
    </div>
  )
}

/** Incoming requests addressed to the viewer, with accept / decline. */
function IncomingRequestsTab() {
  const { t } = useTranslation()
  const list = useListState('incoming')
  const requests = useIncomingRequests(list.params)

  return (
    <Card>
      <ListFrame
        meta={requests.data?.meta}
        loading={requests.isLoading}
        error={requests.isError}
        search={list.search}
        sort={list.sort}
        onSearch={list.onSearch}
        onSort={list.onSort}
        onRetry={() => void requests.refetch()}
        onPaging={list.onPaging}
        testId="incoming-requests"
      >
        <div className="connections-list-rows">
          {requests.data?.data.map((request) => <IncomingRequestCard key={request.id} request={request} />)}
        </div>
      </ListFrame>
      <p className="connections-hint">{t.connections.incomingHint}</p>
    </Card>
  )
}

/** Requests the viewer sent, with cancel actions. */
function OutgoingRequestsTab() {
  const { t } = useTranslation()
  const list = useListState('outgoing')
  const requests = useOutgoingRequests(list.params)

  return (
    <Card>
      <ListFrame
        meta={requests.data?.meta}
        loading={requests.isLoading}
        error={requests.isError}
        search={list.search}
        sort={list.sort}
        onSearch={list.onSearch}
        onSort={list.onSort}
        onRetry={() => void requests.refetch()}
        onPaging={list.onPaging}
        testId="outgoing-requests"
      >
        <div className="connections-list-rows">
          {requests.data?.data.map((request) => <OutgoingRequestCard key={request.id} request={request} />)}
        </div>
      </ListFrame>
      <p className="connections-hint">{t.connections.outgoingHint}</p>
    </Card>
  )
}

/** The viewer's own connections with remove actions. */
function MyConnectionsTab() {
  const { t, locale } = useTranslation()
  const toast = useToast()
  const list = useListState('connections')
  const connections = useMyConnections(list.params)
  const removeConnection = useRemoveConnection()
  const [removing, setRemoving] = useState<{ id: number; name: string } | null>(null)

  return (
    <Card>
      <ListFrame
        meta={connections.data?.meta}
        loading={connections.isLoading}
        error={connections.isError}
        search={list.search}
        sort={list.sort}
        onSearch={list.onSearch}
        onSort={list.onSort}
        onRetry={() => void connections.refetch()}
        onPaging={list.onPaging}
        testId="my-connections"
      >
        <div className="connections-list-rows">
          {connections.data?.data.map((row) => (
            <SocialUserCard
              key={row.user.id}
              user={row.user}
              relationship={row.relationship}
              caption={row.connected_at ? formatLocaleDate(row.connected_at, locale) : null}
              actions={(
                <>
                  {row.relationship ? <ConnectionStatusBadge relationship={row.relationship} /> : null}
                  <Button
                    variant="ghost"
                    size="sm"
                    loading={removeConnection.isPending && removeConnection.variables === row.user.id}
                    disabled={removeConnection.isPending}
                    onClick={() => setRemoving({ id: row.user.id, name: row.user.display_name })}
                  >
                    <UserMinus size={14} aria-hidden="true" />
                    {t.connections.rows.remove}
                  </Button>
                </>
              )}
            />
          ))}
        </div>
      </ListFrame>

      <ConfirmDialog
        open={removing !== null}
        danger
        title={interpolate(t.connections.rows.removeTitle, { name: removing?.name ?? '' })}
        description={t.connections.rows.removeCopy}
        confirmLabel={t.connections.rows.remove}
        cancelLabel={t.actions.cancel}
        loading={removeConnection.isPending}
        onClose={() => setRemoving(null)}
        onConfirm={() => {
          if (!removing) return
          removeConnection.mutate(removing.id, {
            onSuccess: () => { setRemoving(null); toast('info', t.connections.rows.removed) },
            onError: () => { setRemoving(null); toast('danger', t.connections.errors.actionFailed) },
          })
        }}
      />
    </Card>
  )
}

/** Real, actionable suggestions from the privacy-aware discovery endpoint. */
function SuggestionsTab() {
  const list = useListState('suggestions')
  const suggestions = useConnectionSuggestions(list.params)

  return (
    <Card>
      <ListFrame
        meta={suggestions.data?.meta}
        loading={suggestions.isLoading}
        error={suggestions.isError}
        search={list.search}
        sort={list.sort}
        onSearch={list.onSearch}
        onSort={list.onSort}
        onRetry={() => void suggestions.refetch()}
        onPaging={list.onPaging}
        testId="connection-suggestions"
        grid
      >
        {suggestions.data?.data.map((profile) => <DiscoveryProfileCard key={profile.id} profile={profile} variant="grid" />)}
      </ListFrame>
    </Card>
  )
}

/** The signed-in member's followers or following list. */
function EdgesTab({ mode }: { mode: 'followers' | 'following' }) {
  const { locale } = useTranslation()
  const self = useOutletContext<{ id: number }>()
  const list = useListState(mode)
  const followers = useFollowers(self.id, list.params, mode === 'followers')
  const following = useFollowing(self.id, list.params, mode === 'following')
  const edges = mode === 'followers' ? followers : following

  return (
    <Card>
      <ListFrame
        meta={edges.data?.meta}
        loading={edges.isLoading}
        error={edges.isError}
        search={list.search}
        sort={list.sort}
        onSearch={list.onSearch}
        onSort={list.onSort}
        onRetry={() => void edges.refetch()}
        onPaging={list.onPaging}
        testId={`edges-${mode}`}
      >
        <div className="connections-list-rows">
          {edges.data?.data.map((row) => (
            <SocialUserCard
              key={row.user.id}
              user={row.user}
              relationship={row.relationship}
              caption={row.followed_at ? formatLocaleDate(row.followed_at, locale) : null}
              actions={row.relationship ? (
                <>
                  <ConnectionStatusBadge relationship={row.relationship} />
                  <FollowButton userId={row.user.id} relationship={row.relationship} />
                  <ConnectionRequestButton userId={row.user.id} relationship={row.relationship} />
                </>
              ) : null}
            />
          ))}
        </div>
      </ListFrame>
    </Card>
  )
}

/** Blocked accounts with unblock actions. */
function BlockedTab() {
  const { t, locale } = useTranslation()
  const toast = useToast()
  const list = useListState('blocked')
  const blocked = useBlockedUsers(list.params)
  const { unblock } = useBlockActions()

  return (
    <Card>
      <ListFrame
        meta={blocked.data?.meta}
        loading={blocked.isLoading}
        error={blocked.isError}
        search={list.search}
        sort={list.sort}
        onSearch={list.onSearch}
        onSort={list.onSort}
        onRetry={() => void blocked.refetch()}
        onPaging={list.onPaging}
        testId="blocked-users"
      >
        <div className="connections-list-rows">
          {blocked.data?.data.map((row) => (
            <article className="social-row" key={row.user.id}>
              <Avatar name={row.user.display_name} src={row.user.avatar_url ?? undefined} />
              <div className="social-row-id">
                <h3 className="social-row-name">{row.user.display_name}</h3>
                <p className="social-row-username">
                  {row.user.username ? `@${row.user.username}` : ''}
                  {row.blocked_at ? ` · ${formatLocaleDate(row.blocked_at, locale)}` : ''}
                </p>
              </div>
              <div className="social-row-actions">
                <Button
                  variant="soft"
                  size="sm"
                  loading={unblock.isPending && unblock.variables === row.user.id}
                  disabled={unblock.isPending}
                  onClick={() => unblock.mutate(row.user.id, {
                    onSuccess: () => toast('info', t.connections.rows.unblocked),
                    onError: () => toast('danger', t.connections.errors.actionFailed),
                  })}
                >
                  <Undo2 size={14} aria-hidden="true" />
                  {t.connections.rows.unblock}
                </Button>
              </div>
            </article>
          ))}
        </div>
      </ListFrame>
      <p className="connections-hint">{t.connections.blockedHint}</p>
    </Card>
  )
}
