import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Bell, BellRing, Check, CheckCheck } from 'lucide-react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Avatar, Badge, Button, Card, CardHeader, EmptyState, ErrorState, Pagination, Skeleton, useToast } from '../../../components/ui'
import { formatLocaleDate, interpolate } from '../../../i18n'
import { useNotificationReads, useNotifications } from '../hooks/useConnections'
import type { SocialListParams, SocialNotification } from '../types'

const PER_PAGE_OPTIONS = [5, 10, 20, 50] as const

/** /notifications workspace — the member's social activity feed. */
export function NotificationsPage() {
  const { t, locale } = useTranslation()
  const toast = useToast()
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(10)
  const reads = useNotificationReads()
  const params: SocialListParams = { page, per_page: perPage }
  const feed = useNotifications(params)

  const meta = feed.data?.meta
  const unreadCount = feed.data?.unread_count ?? 0

  return (
    <div className="page notifications-page" data-testid="notifications-page">
      <CardHeader
        eyebrow={t.nav.social}
        title={t.notifications.title}
        action={unreadCount > 0 ? (
          <Badge tone="primary">
            <BellRing size={12} aria-hidden="true" />
            {interpolate(t.notifications.unreadBadge, { count: unreadCount })}
          </Badge>
        ) : null}
      />
      <p className="page-copy">{t.notifications.intro}</p>

      <div className="notifications-actions">
        {unreadCount > 0 ? (
          <Button
            variant="soft"
            size="sm"
            loading={reads.markAllRead.isPending}
            disabled={reads.markAllRead.isPending}
            onClick={() => reads.markAllRead.mutate(undefined, {
              onSuccess: () => toast('success', t.notifications.allRead),
              onError: () => toast('danger', t.notifications.errors.actionFailed),
            })}
          >
            <CheckCheck size={14} aria-hidden="true" />
            {t.notifications.markAllRead}
          </Button>
        ) : null}
      </div>

      <Card>
        <div className="connections-list" data-testid="notifications-list">
          <div className="connections-list-top">
            <p className="connections-count">{interpolate(t.notifications.count, { count: meta?.total ?? 0 })}</p>
          </div>

          {feed.isLoading ? (
            <div className="connections-list-rows">
              <Skeleton className="social-row-skeleton" />
              <Skeleton className="social-row-skeleton" />
              <Skeleton className="social-row-skeleton" />
            </div>
          ) : null}

          {feed.isError ? (
            <ErrorState
              title={t.notifications.errors.loadFailed}
              description={t.notifications.errors.loadFailedCopy}
              action={<Button variant="soft" size="sm" onClick={() => void feed.refetch()}>{t.notifications.errors.retry}</Button>}
            />
          ) : null}

          {!feed.isLoading && !feed.isError && (meta?.total ?? 0) === 0 ? (
            <EmptyState icon={<Bell size={20} aria-hidden="true" />} title={t.notifications.empty.title} description={t.notifications.empty.copy} />
          ) : null}

          {!feed.isLoading && !feed.isError ? (
            <div className="connections-list-rows">
              {feed.data?.data.map((notification) => (
                <NotificationRow
                  key={notification.id}
                  notification={notification}
                  locale={locale}
                  pending={reads.markRead.isPending && reads.markRead.variables === notification.id}
                  onMarkRead={() => reads.markRead.mutate(notification.id, {
                    onSuccess: () => toast('info', t.notifications.marked),
                    onError: () => toast('danger', t.notifications.errors.actionFailed),
                  })}
                />
              ))}
            </div>
          ) : null}

          {meta && meta.last_page > 1 ? (
            <Pagination
              page={meta.current_page}
              pageSize={meta.per_page}
              total={meta.total}
              onPage={setPage}
              onPageSize={(size) => { setPage(1); setPerPage(size) }}
              pageSizeOptions={PER_PAGE_OPTIONS}
              rowsPerPageLabel={t.notifications.pagination.rowsPerPage}
              rangeLabel={interpolate(t.notifications.pagination.range, { from: Math.min(meta.total, (meta.current_page - 1) * meta.per_page + 1), to: Math.min(meta.total, meta.current_page * meta.per_page), total: meta.total })}
            />
          ) : null}
        </div>
      </Card>
    </div>
  )
}

/** One notification row; unread rows carry a mark-as-read action. */
function NotificationRow({ notification, locale, pending, onMarkRead }: {
  notification: SocialNotification
  locale: 'en' | 'my'
  pending: boolean
  onMarkRead: () => void
}) {
  const { t } = useTranslation()
  const unread = notification.read_at === null
  const name = notification.actor?.display_name ?? ''
  const copy = copyFor(notification, name, t)

  return (
    <article className={`notification-row ${unread ? 'is-unread' : ''}`}>
      <Avatar name={name || '?'} />
      <div className="notification-copy">
        <p>
          {copy}
          {unread ? <span className="ui-badge ui-badge-primary">{t.notifications.unread}</span> : null}
        </p>
        <p className="notification-date">{notification.created_at ? formatLocaleDate(notification.created_at, locale) : ''}</p>
      </div>
      <div className="notification-actions">
        <Link className="ui-button ui-button-soft ui-button-sm" to={notification.url}>{t.notifications.open}</Link>
        {unread ? (
          <Button variant="ghost" size="sm" loading={pending} disabled={pending} onClick={onMarkRead}>
            <Check size={14} aria-hidden="true" />
            {t.notifications.markRead}
          </Button>
        ) : null}
      </div>
    </article>
  )
}

/** Map a backend notification type + actor into localized copy. */
function copyFor(notification: SocialNotification, name: string, t: ReturnType<typeof useTranslation>['t']): string {
  switch (notification.type) {
    case 'new_follower':
      return interpolate(t.notifications.types.new_follower, { name })
    case 'connection_request':
      return interpolate(t.notifications.types.connection_request, { name })
    case 'connection_accepted':
      return interpolate(t.notifications.types.connection_accepted, { name })
    default:
      return t.notifications.types.generic
  }
}
