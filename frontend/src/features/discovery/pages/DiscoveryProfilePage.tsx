import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, CalendarDays, Flag, Globe, MapPin, ShieldOff, Sparkles } from 'lucide-react'
import { useEffect } from 'react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Avatar, Badge, Button, Card, CardHeader, InterestChip, Tooltip, useToast } from '../../../components/ui'
import { formatLocaleDate, interpolate } from '../../../i18n'
import { useDiscoveryFeedback, useDiscoveryProfile } from '../hooks/useDiscovery'
import { MatchScoreIndicator } from '../components/MatchScoreIndicator'
import { DiscoveryFeedbackMenu } from '../components/DiscoveryFeedbackMenu'
import type { DiscoveryProfileDetail, FeedbackType } from '../types'

/** Route /people/:username — privacy-safe public profile preview. */
export function DiscoveryProfilePage() {
  const { username = '' } = useParams()
  const { t, locale } = useTranslation()
  const toast = useToast()
  const profile = useDiscoveryProfile(username)
  const feedback = useDiscoveryFeedback()

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

  const data: DiscoveryProfileDetail = profile.data
  const onFeedback = (type: FeedbackType) => {
    feedback.give.mutate({ targetUserId: data.id, feedbackType: type }, {
      onSuccess: () => toast('success', type === 'hide_profile' ? t.discovery.feedback.hiddenTitle : t.discovery.feedback.notInterestedTitle, t.discovery.feedback.hiddenCopy),
      onError: () => toast('danger', t.discovery.feedback.failed),
    })
  }

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
            <DiscoveryFeedbackMenu hidden={false} busy={feedback.give.isPending} onFeedback={onFeedback} />
          </div>
        </div>
        {data.bio ? <p className="discovery-profile-bio">{data.bio}</p> : <p className="discovery-profile-bio is-muted">{t.discovery.card.noBio}</p>}
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
        {/* Block and report need Phase 02+ safety endpoints; shown deliberately unavailable. */}
        <Tooltip content={t.discovery.profile.safetyPending} position="top">
          <Button variant="soft" size="sm" disabled><ShieldOff size={15} aria-hidden="true" /> {t.discovery.profile.blocking}</Button>
        </Tooltip>
        <Tooltip content={t.discovery.profile.safetyPending} position="top">
          <Button variant="soft" size="sm" disabled><Flag size={15} aria-hidden="true" /> {t.discovery.profile.report}</Button>
        </Tooltip>
      </div>
    </div>
  )
}
