import { useTranslation } from '../../../layouts/AppLayouts'
import { Avatar, Button, useToast } from '../../../components/ui'
import { formatLocaleDate } from '../../../i18n'
import { useRequestResponse } from '../hooks/useConnections'
import type { IncomingRequest, OutgoingRequest } from '../types'
import { Link } from 'react-router-dom'

/** Shared row shell for both request directions. */
function RequestShell({ name, username, avatar, sentAt, actions }: { name: string; username: string | null; avatar?: string | null; sentAt: string | null; actions: React.ReactNode }) {
  const { locale, t } = useTranslation()
  return (
    <article className="social-row">
      <Avatar name={name} src={avatar ?? undefined} />
      <div className="social-row-id">
        <h3 className="social-row-name">{username ? <Link to={`/people/${username}`}>{name}</Link> : name}</h3>
        <p className="social-row-username">
          {username ? `@${username}` : ''}
          {sentAt ? ` · ${formatLocaleDate(sentAt, locale)}` : ''}
        </p>
      </div>
      <div className="social-row-actions">
        {username ? <Link className="ui-button ui-button-secondary ui-button-sm social-row-profile" to={`/people/${username}`}>{t.social.viewProfile}</Link> : null}
        {actions}
      </div>
    </article>
  )
}

/** Incoming request with accept and decline actions. */
export function IncomingRequestCard({ request }: { request: IncomingRequest }) {
  const { t } = useTranslation()
  const toast = useToast()
  const respond = useRequestResponse()
  const sender = request.sender

  if (!sender) return null

  const act = (action: 'accept' | 'decline') => {
    respond[action].mutate(request.id, {
      onSuccess: () => toast('success', t.connections.request[action === 'accept' ? 'accepted' : 'declined']),
      onError: () => toast('danger', t.connections.errors.actionFailed),
    })
  }

  return (
    <RequestShell
      name={sender.display_name}
      username={sender.username}
      avatar={sender.avatar_url}
      sentAt={request.created_at}
      actions={(
        <>
          <Button size="sm" loading={respond.accept.isPending} disabled={respond.accept.isPending || respond.decline.isPending} onClick={() => act('accept')}>
            {t.connections.request.accept}
          </Button>
          <Button variant="soft" size="sm" loading={respond.decline.isPending} disabled={respond.accept.isPending || respond.decline.isPending} onClick={() => act('decline')}>
            {t.connections.request.decline}
          </Button>
        </>
      )}
    />
  )
}

/** Outgoing request with a cancel action and confirmation. */
export function OutgoingRequestCard({ request }: { request: OutgoingRequest }) {
  const { t } = useTranslation()
  const toast = useToast()
  const respond = useRequestResponse()
  const receiver = request.receiver

  if (!receiver) return null

  return (
    <RequestShell
      name={receiver.display_name}
      username={receiver.username}
      avatar={receiver.avatar_url}
      sentAt={request.created_at}
      actions={(
        <Button
          variant="ghost"
          size="sm"
          loading={respond.cancel.isPending}
          disabled={respond.cancel.isPending}
          onClick={() => respond.cancel.mutate(request.id, {
            onSuccess: () => toast('info', t.connections.request.cancelled),
            onError: () => toast('danger', t.connections.errors.actionFailed),
          })}
        >
          {t.connections.request.cancel}
        </Button>
      )}
    />
  )
}
