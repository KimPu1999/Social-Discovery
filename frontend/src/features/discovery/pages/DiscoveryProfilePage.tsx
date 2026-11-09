import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, CalendarDays, Flag, Globe, MapPin, ShieldOff, ShieldCheck, Sparkles, Users } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Avatar, Badge, Button, Card, CardHeader, ConfirmDialog, InterestChip, Tooltip, useToast } from '../../../components/ui'
import { formatLocaleDate, formatLocaleNumber, interpolate } from '../../../i18n'
import { useDiscoveryFeedback, useDiscoveryProfile } from '../hooks/useDiscovery'
import { MatchScoreIndicator } from '../components/MatchScoreIndicator'
import { DiscoveryFeedbackMenu } from '../components/DiscoveryFeedbackMenu'
import { useBlockActions, useRelationship } from '../../connections/hooks/useConnections'
import { FollowButton } from '../../connections/components/FollowButton'
import { ConnectionRequestButton } from '../../connections/components/ConnectionRequestButton'
import { ConnectionStatusBadge } from '../../connections/components/ConnectionStatusBadge'
import type { DiscoveryProfileDetail, FeedbackType } from '../types'

/** Route /people/:username — privacy-safe public profile preview. */
export function DiscoveryProfilePage() {
  const { username = '' } = useParams()
  const { t } = useTranslation()
  const toast = useToast()
  const profile = useDiscoveryProfile(username)

  // Errors other than the uniform privacy-safe 404 surface a generic message (no enumeration).
  const notFound = (profile.error as { response?: { status?: number } } | null)?.response?.status === 404
  const unexpectedError = !profile.isLoading && profile.isError && !notFound
  useEffect(() => {
    if (unexpectedError) toast('danger', t.discovery.error.title, t.discovery.error.copy)
  }, [unexpectedError, toast, t.discovery.error.title, t.discovery.error.copy])

  if (profile.isLoading) {
    return <div className="discovery-page"><p className="discovery-loading">{t.discovery.profile.loading}</p></div>
  }

  // Privacy-safe: any non-eligible profile (private, suspended, blocked, hidden) renders identically.
  if (unexpectedError || !profile.data) {
    return (
      <div className="discovery-page">
        <Link className="text-button" to="/people"><ArrowLeft size={14} aria-hidden="true" /> {t.discovery.profile.backToPeople}</Link>
        <Card className="discovery-error-card">
          <h2>{t.discovery.profile.unavailable}</h2>
          <p>{t.discovery.profile.unavailableCopy}</p>
          <Link className="ui-button ui-button-soft ui-button-sm" to="/people">{t.discovery.profile.backToPeople}</Link>
        </Card>
      </div>
    )
  }

  return <ProfileView data={profile.data} />
}

/** Fully-loaded profile view; separate component so its social hooks stay unconditional. */
function ProfileView({ data }: { data: DiscoveryProfileDetail }) {
  const { t, locale } = useTranslation()
  const toast = useToast()
  const feedback = useDiscoveryFeedback()
  const stats = useRelationship(data.id)
  const blockActions = useBlockActions()
  const [blocking, setBlocking] = useState(false)
  const relationship = data.relationship ?? null

  const onFeedback = (type: FeedbackType) => {
    feedback.give.mutate({ targetUserId: data.id, feedbackType: type }, {
      onSuccess: () => toast('success', type === 'hide_profile' ? t.discovery.feedback.hiddenTitle : t.discovery.feedback.notInterestedTitle, t.discovery.feedback.hiddenCopy),
      onError: () => toast('danger', t.discovery.feedback.failed),
    })
  }

  const counts = stats.data?.counts

  return (
    <div className="discovery-page">
      <nav className="discovery-profile-back" aria-label={t.discovery.profile.backToPeople}>
        <Link className="text-button" to="/people"><ArrowLeft size={14} aria-hidden="true" /> {t.discovery.profile.backToPeople}</Link>
      </nav>

      <Card className="discovery-profile-hero">
        <div className="discovery-profile-cover" aria-hidden="true" />
        <div className="discovery-profile-head">
          <Avatar name={data.display_name} src={data.avatar_url ?? undefined} size="xl" />
          <div className="discovery-profile-id">
            <h1>{data.display_name}</h1>
            <p className="discovery-card-username">@{data.username}</p>
            <div className="discovery-profile-meta">
              <Badge tone="neutral">{data.visibility === 'public' ? t.discovery.profile.visibilityPublic : t.discovery.profile.visibilityPrivate}</Badge>
              {data.joined_at ? <span className="discovery-profile-joined"><CalendarDays size={14} aria-hidden="true" /> {interpolate(t.discovery.profile.joined, { date: formatLocaleDate(data.joined_at, locale) })}</span> : null}
              {data.website_url ? <a className="discovery-profile-website" href={data.website_url} target="_blank" rel="noreferrer noopener"><Globe size={14} aria-hidden="true" /> {t.discovery.profile.website}</a> : null}
              {data.location_visible && data.location ? <span className="discovery-profile-location"><MapPin size={14} aria-hidden="true" /> {data.location}</span> : null}
            </div>
          </div>
          <div className="discovery-card-tools">
            <MatchScoreIndicator match={data.match} />
            {relationship ? <ConnectionStatusBadge relationship={relationship} /> : null}
            <DiscoveryFeedbackMenu hidden={false} busy={feedback.give.isPending} onFeedback={onFeedback} />
          </div>
        </div>
        {data.bio ? <p className="discovery-profile-bio">{data.bio}</p> : <p className="discovery-profile-bio is-muted">{t.discovery.card.noBio}</p>}

        {/* Live social actions + real counts (Phase 05). Rendered only from
            what the authorization layer chose to expose; nothing faked locally. */}
        {relationship && relationship.can_follow ? <FollowButton userId={data.id} relationship={relationship} size="md" /> : null}
        {relationship ? <ConnectionRequestButton userId={data.id} relationship={relationship} size="md" /> : null}
        {counts ? (
          <dl className="discovery-profile-social-counts">
            <div><dt>{t.connections.tabs.followers}</dt><dd>{formatLocaleNumber(counts.followers, locale)}</dd></div>
            <div><dt>{t.connections.tabs.following}</dt><dd>{formatLocaleNumber(counts.following, locale)}</dd></div>
            <div><dt><Users size={11} aria-hidden="true" /> {t.connections.tabs.connections}</dt><dd>{formatLocaleNumber(counts.connections, locale)}</dd></div>
            {stats.data && stats.data.mutual ? (
              <div><dt>{t.connections.mutualTitle}</dt><dd>{formatLocaleNumber(stats.data.mutual.count, locale)}</dd></div>
            ) : null}
          </dl>
        ) : null}
      </Card>

      <Card className="discovery-profile-section">
        <CardHeader title={t.discovery.profile.interests} eyebrow={data.interests_visible ? undefined : t.discovery.profile.interestsHidden} />
        {data.match ? (
          <p className="discovery-profile-shared"><Sparkles size={15} aria-hidden="true" /> {t.discovery.profile.sharedTitle}: {data.match.shared_count} · {Math.round(data.match.score)}%</p>
        ) : null}
        {data.interests_visible === false ? (
          <p className="discovery-profile-bio is-muted">{t.discovery.profile.interestsHidden}</p>
        ) : (
          <div className="discovery-card-interests">
            {data.interests.map((interest) => <InterestChip key={interest.id} selected={interest.shared_with_viewer}>{interest.name}</InterestChip>)}
          </div>
        )}
      </Card>

      <div className="discovery-profile-safety">
        {relationship?.is_blocked_by_me ? (
          <Button
            variant="soft"
            size="sm"
            loading={blockActions.unblock.isPending}
            disabled={blockActions.unblock.isPending}
            onClick={() => blockActions.unblock.mutate(data.id, {
              onSuccess: () => toast('info', t.connections.rows.unblocked),
              onError: () => toast('danger', t.connections.errors.actionFailed),
            })}
          >
            <ShieldCheck size={15} aria-hidden="true" /> {t.connections.blocking.unblock}
          </Button>
        ) : (
          <Button variant="soft" size="sm" onClick={() => setBlocking(true)}>
            <ShieldOff size={15} aria-hidden="true" /> {t.discovery.profile.blocking}
          </Button>
        )}
        {/* Report waits for the Phase 02+ safety endpoints. */}
        <Tooltip content={t.discovery.profile.safetyPending} position="top">
          <Button variant="soft" size="sm" disabled><Flag size={15} aria-hidden="true" /> {t.discovery.profile.report}</Button>
        </Tooltip>
      </div>

      <ConfirmDialog
        open={blocking}
        danger
        title={interpolate(t.connections.blocking.title, { name: data.display_name })}
        description={t.connections.blocking.copy}
        confirmLabel={t.discovery.profile.blocking}
        cancelLabel={t.actions.cancel}
        loading={blockActions.block.isPending}
        onClose={() => setBlocking(false)}
        onConfirm={() => {
          blockActions.block.mutate(data.id, {
            onSuccess: () => { setBlocking(false); toast('info', t.connections.blocking.done) },
            onError: () => { setBlocking(false); toast('danger', t.connections.errors.actionFailed) },
          })
        }}
      />
    </div>
  )
}
