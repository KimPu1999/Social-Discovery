import { useState } from 'react'
import { Link } from 'react-router-dom'
import { CalendarDays } from 'lucide-react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Avatar, Button, ConfirmDialog, InterestChip, Tooltip, useToast } from '../../../components/ui'
import { formatLocaleDate } from '../../../i18n'
import { MatchExplanation, MatchScoreIndicator } from './MatchScoreIndicator'
import { DiscoveryFeedbackMenu } from './DiscoveryFeedbackMenu'
import { FollowButton } from '../../connections/components/FollowButton'
import { ConnectionRequestButton } from '../../connections/components/ConnectionRequestButton'
import { useBlockActions } from '../../connections/hooks/useConnections'
import type { DiscoveryProfile, FeedbackType } from '../types'

export type DiscoveryProfileCardProps = {
  profile: DiscoveryProfile
  onFeedback?: (profile: DiscoveryProfile, type: FeedbackType) => void
  onUndoFeedback?: (profile: DiscoveryProfile) => void
  hidden?: boolean
  busy?: boolean
  variant?: 'grid' | 'list' | 'recommended'
}

/**
 * Reusable discovery profile card. Stores (email, status, role, location,
 * birth dates) never exist on the payload; the card renders only what the
 * API chose to expose.
 */
export function DiscoveryProfileCard({ profile, onFeedback, onUndoFeedback, hidden = false, busy = false, variant = 'grid' }: DiscoveryProfileCardProps) {
  const { t, locale } = useTranslation()
  const toast = useToast()
  const blockActions = useBlockActions()
  const [blocking, setBlocking] = useState(false)
  const sharedInterests = profile.interests.filter((interest) => interest.shared_with_viewer)
  const otherInterests = profile.interests.filter((interest) => !interest.shared_with_viewer).slice(0, Math.max(0, 4 - sharedInterests.length))

  return (
    <article className={`discovery-card discovery-card-${variant}`}>
      <header className="discovery-card-head">
        <Avatar name={profile.display_name} src={profile.avatar_url ?? undefined} size="lg" />
        <div className="discovery-card-id">
          <h3 className="discovery-card-name">
            <Link to={`/people/${profile.username}`}>{profile.display_name}</Link>
          </h3>
          <p className="discovery-card-username">@{profile.username}</p>
          {profile.bio ? <p className="discovery-card-bio">{profile.bio}</p> : <p className="discovery-card-bio is-muted">{t.discovery.card.noBio}</p>}
        </div>
        <div className="discovery-card-tools">
          <MatchScoreIndicator match={profile.match} />
          {onFeedback ? <DiscoveryFeedbackMenu hidden={hidden} busy={busy || blockActions.block.isPending} onFeedback={(type) => onFeedback(profile, type)} onUndoFeedback={onUndoFeedback ? () => onUndoFeedback(profile) : undefined} onBlock={profile.relationship ? () => setBlocking(true) : undefined} /> : null}
        </div>
      </header>

      {sharedInterests.length ? <p className="discovery-card-shared">{interpolateShared(t, sharedInterests.length)}</p> : null}
      {profile.match ? <MatchExplanation match={profile.match} /> : null}

      <div className="discovery-card-interests">
        {sharedInterests.map((interest) => <InterestChip key={interest.id} selected>{interest.name}</InterestChip>)}
        {otherInterests.map((interest) => <InterestChip key={interest.id}>{interest.name}</InterestChip>)}
      </div>

      <footer className="discovery-card-actions">
        {profile.joined_at ? <span className="discovery-card-joined"><CalendarDays size={13} aria-hidden="true" />{formatLocaleDate(profile.joined_at, locale)}</span> : null}
        <div className="discovery-card-buttons">
          {/* Phase 05: live follow / connect controls when the API sent
              relationship state; otherwise keep the clearly-unavailable hint. */}
          {profile.relationship ? (
            <>
              <FollowButton userId={profile.id} relationship={profile.relationship} />
              <ConnectionRequestButton userId={profile.id} relationship={profile.relationship} />
            </>
          ) : (
            <Tooltip content={t.discovery.card.connectUnavailable} position="top">
              <Button variant="soft" size="sm" disabled>{t.discovery.card.connect}</Button>
            </Tooltip>
          )}
          <Link className="ui-button ui-button-secondary ui-button-sm" to={`/people/${profile.username}`}>{t.discovery.card.viewProfile}</Link>
        </div>
      </footer>
      <ConfirmDialog
        open={blocking}
        danger
        title={t.connections.blocking.title.replace('{name}', profile.display_name)}
        description={t.connections.blocking.copy}
        confirmLabel={t.discovery.profile.blocking}
        cancelLabel={t.actions.cancel}
        loading={blockActions.block.isPending}
        onClose={() => setBlocking(false)}
        onConfirm={() => blockActions.block.mutate(profile.id, {
          onSuccess: () => { setBlocking(false); toast('info', t.connections.blocking.done) },
          onError: () => { setBlocking(false); toast('danger', t.connections.errors.actionFailed) },
        })}
      />
    </article>
  )
}

function interpolateShared(t: ReturnType<typeof useTranslation>['t'], count: number): string {
  return count === 1 ? t.discovery.match.sharedOne : count + ' ' + t.social.sharedInterests
}
