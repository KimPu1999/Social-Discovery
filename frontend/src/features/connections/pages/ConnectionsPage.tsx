import { useState } from 'react'
import { useOutletContext, useSearchParams } from 'react-router-dom'
import { Search, UserMinus, Undo2 } from 'lucide-react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Avatar, Badge, Button, Card, CardHeader, EmptyState, ErrorState, Pagination, Skeleton, Tabs, useToast, ConfirmDialog } from '../../../components/ui'
import { formatLocaleDate, interpolate } from '../../../i18n'
import {
  useBlockedUsers,
  useFollowers,
  useFollowing,
  useIncomingRequests,
  useMyConnections,
  useOutgoingRequests,
  useRemoveConnection,
  useBlockActions,
} from '../hooks/useConnections'
import { ConnectionStatusBadge } from '../components/ConnectionStatusBadge'
import { FollowButton } from '../components/FollowButton'
import { SocialUserCard } from '../components/SocialUserCard'
import { IncomingRequestCard, OutgoingRequestCard } from '../components/RequestCards'
import { CONNECTION_TABS, type ConnectionTab, type SocialListParams } from '../types'

const PER_PAGE_OPTIONS = [5, 10, 20, 50] as const

function isConnectionTab(value: string): value is ConnectionTab {
  return (CONNECTION_TABS as readonly string[]).includes(value)
}

/** Shared per-tab list state so every tab keeps its own page/page size. */
function useListState() {
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(10)
  const [search, setSearch] = useState('')
  const params: SocialListParams = { page, per_page: perPage, ...(search.trim() ? { search: search.trim() } : {}) }

  const onPaging = (nextPage: number, nextPerPage: number) => { setPage(nextPage); setPerPage(nextPerPage) }

  return { page, perPage, search, params, setSearch, onPaging }
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
  const pendingCount = incoming.data?.meta.total ?? 0

  const changeTab = (next: string) => setSearchParams(next === 'connections' ? {} : { tab: next })

  return (
    <div className="page connections-page" data-testid="connections-page">
      <CardHeader
        eyebrow={t.nav.social}
        title={t.connections.title}
        action={pendingCount > 0 ? <Badge tone="primary">{interpolate(t.connections.pendingCount, { count: pendingCount })}</Badge> : null}
      />
      <p className="page-copy">{t.connections.intro}</p>

      <Tabs
        label={t.connections.tabsLabel}
        items={CONNECTION_TABS.map((id) => ({ id, label: t.connections.tabs[id] }))}
        active={tab}
        onChange={changeTab}
      />

      {tab === 'incoming' ? <IncomingRequestsTab /> : null}
      {tab === 'outgoing' ? <OutgoingRequestsTab /> : null}
      {tab === 'connections' ? <MyConnectionsTab /> : null}
      {tab === 'followers' ? <EdgesTab mode="followers" /> : null}
      {tab === 'following' ? <EdgesTab mode="following" /> : null}
      {tab === 'blocked' ? <BlockedTab /> : null}
    </div>
  )
}

/** Shared chrome (toolbar, states, pagination) around each tab's list. */
function ListFrame({
  meta, loading, error, search, onSearch, onRetry, onPaging, children, testId,
}: {
  meta?: { current_page: number; last_page: number; per_page: number; total: number }
  loading: boolean
  error: boolean
  search: string
  onSearch: (value: string) => void
  onRetry: () => void
  onPaging: (page: number, perPage: number) => void
  children: React.ReactNode
  testId: string
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
        {meta && meta.total > 0 ? <p className="connections-count">{interpolate(t.connections.resultCount, { count: meta.total })}</p> : null}
      </div>

      {loading ? <ListSkeleton /> : null}
      {error ? <ErrorState title={t.connections.errors.loadFailed} description={t.connections.errors.loadFailedCopy} action={<Button variant="soft" size="sm" onClick={onRetry}>{t.connections.errors.retry}</Button>} /> : null}
      {!loading && !error && meta?.total === 0 ? <EmptyState icon={<UserMinus size={20} aria-hidden="true" />} title={t.connections.empty.title} description={t.connections.empty.copy} /> : null}
      {!loading && !error ? children : null}

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
  const list = useListState()
  const requests = useIncomingRequests(list.params)

  return (
    <Card>
      <ListFrame
        meta={requests.data?.meta}
        loading={requests.isLoading}
        error={requests.isError}
        search={list.search}
        onSearch={(value) => { list.setSearch(value) }}
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
  const list = useListState()
  const requests = useOutgoingRequests(list.params)

  return (
    <Card>
      <ListFrame
        meta={requests.data?.meta}
        loading={requests.isLoading}
        error={requests.isError}
        search={list.search}
        onSearch={(value) => { list.setSearch(value) }}
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
  const list = useListState()
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
        onSearch={(value) => { list.setSearch(value) }}
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

/** The signed-in member's followers or following list. */
function EdgesTab({ mode }: { mode: 'followers' | 'following' }) {
  const { locale } = useTranslation()
  const self = useOutletContext<{ id: number }>()
  const list = useListState()
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
        onSearch={(value) => { list.setSearch(value) }}
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
  const list = useListState()
  const blocked = useBlockedUsers(list.params)
  const { unblock } = useBlockActions()

  return (
    <Card>
      <ListFrame
        meta={blocked.data?.meta}
        loading={blocked.isLoading}
        error={blocked.isError}
        search={list.search}
        onSearch={(value) => { list.setSearch(value) }}
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
