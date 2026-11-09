import { CalendarDays, HeartHandshake, Sparkles, Users } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { authService } from '../services/authService'
import { profileService } from '../services/profileService'
import { useTranslation } from '../layouts/AppLayouts'
import { Avatar, Card, CardHeader, EmptyState, Skeleton } from '../components/ui'
import { useDiscoveryOverview, useRecommendedPeople } from '../features/discovery/hooks/useDiscovery'
import { DiscoveryProfileCard } from '../features/discovery/components/DiscoveryProfileCard'
import type { DiscoveryProfile } from '../features/discovery/types'

const hourOf = (date: Date) => date.getHours()

function useGreeting(): 'greeting' | 'greetingAfternoon' | 'greetingEvening' {
  const [hour] = useState(() => hourOf(new Date()))
  return hour < 12 ? 'greeting' : hour < 18 ? 'greetingAfternoon' : 'greetingEvening'
}

/** Route /dashboard — welcome, shortcuts, live discovery data, empty states for future modules. */
export function DashboardPage() {
  const { t, locale } = useTranslation()
  const greetingKey = useGreeting()
  const user = useQuery({ queryKey: ['me'], queryFn: authService.me, staleTime: 60_000 })
  const profile = useQuery({ queryKey: ['profile'], queryFn: profileService.get, retry: false })
  const overview = useDiscoveryOverview()
  const enabled = overview.data?.discovery_enabled ?? false
  const recommended = useRecommendedPeople(enabled)

  const firstName = user.data?.user.name.split(' ')[0] ?? 'there'
  const completion = profile.data ? Math.min(100, Math.round(([profile.data.display_name, profile.data.bio, profile.data.location, profile.data.avatar_url, profile.data.interests?.length ? 'interests' : ''].filter(Boolean).length / 5) * 100)) : 0
  const shortcuts = [
    { icon: Users, title: t.dashboard.people, path: '/people' },
    { icon: HeartHandshake, title: t.dashboard.interests, path: '/discover' },
    { icon: Users, title: t.dashboard.communities, path: '/communities' },
    { icon: CalendarDays, title: t.dashboard.events, path: '/events' },
  ]
  const suggestions: DiscoveryProfile[] = recommended.data ?? []

  return (
    <section className="page-shell dashboard-page">
      {/* Section 1 — welcome panel */}
      <div className="dashboard-welcome">
        <div>
          <p className="eyebrow">{t.dashboard.eyebrow}</p>
          <h1>{t.dashboard[greetingKey]}, {firstName}.</h1>
          <p className="page-description">{t.dashboard.intro}</p>
          <div className="dashboard-welcome-actions">
            <Link className="ui-button ui-button-primary ui-button-md" to="/discover">{t.dashboard.people}</Link>
            <Link className="ui-button ui-button-ghost ui-button-md" to="/profile">{t.dashboard.editProfile}</Link>
          </div>
        </div>
        <Avatar name={user.data?.user.name ?? firstName} src={profile.data?.avatar_url} size="xl" />
      </div>

      {/* Section 2 — discovery shortcuts */}
      <Card className="dashboard-shortcuts">
        <CardHeader eyebrow={t.dashboard.shortcuts} title={t.dashboard.shortcutsCopy} />
        <div className="shortcut-grid">
          {shortcuts.map(({ icon: Icon, title, path }) => (
            <Link key={path} className="shortcut-card" to={path}>
              <span className="shortcut-icon"><Icon size={18} /></span>
              <span><strong>{title}</strong><small>{locale === 'my' ? 'စတင်ရန် ရွေးပါ' : 'Explore the next layer'}</small></span>
              <span className="shortcut-arrow" aria-hidden="true">→</span>
            </Link>
          ))}
        </div>
      </Card>

      <div className="dashboard-columns">
        <div className="dashboard-main-column">
          {/* Section 3 — suggested people (live recommendations) */}
          <Card>
            <CardHeader
              eyebrow={t.dashboard.suggestions}
              title={t.dashboard.suggestionsCopy}
              action={<Link className="ui-button ui-button-ghost ui-button-sm" to="/discover">{t.nav.discover}</Link>}
            />
            {recommended.isLoading ? <div className="dashboard-card-skeletons"><Skeleton className="skeleton-line" /><Skeleton className="skeleton-line" /></div>
              : suggestions.length ? <div className="dashboard-suggestion-list">{suggestions.slice(0, 3).map((suggestion) => <DiscoveryProfileCard key={suggestion.id} profile={suggestion} variant="recommended" />)}</div>
                : <EmptyState icon={<Users size={23} />} title={t.empty.title} description={t.dashboard.suggestionsCopy} />}
          </Card>

          {/* Section 6 — recent activity placeholder until the feed phase */}
          <Card>
            <CardHeader eyebrow={t.dashboard.activity} title={t.dashboard.activityCopy} />
            <EmptyState icon={<Sparkles size={23} />} title={t.social.suggestion} description={t.dashboard.activityCopy} />
          </Card>
        </div>

        <div className="dashboard-side-column">
          {/* Profile completion card */}
          <Card className="profile-progress-card">
            <CardHeader eyebrow={t.dashboard.profile} title={`${completion}%`} />
            <div className="progress-track"><span style={{ width: `${completion}%` }} /></div>
            <p>{t.dashboard.profileCopy}</p>
            <div className="dashboard-progress-actions">
              <Link className="ui-button ui-button-soft ui-button-sm" to="/profile">{t.dashboard.editProfile}</Link>
              <Link className="ui-button ui-button-ghost ui-button-sm" to="/settings/discovery">{t.discovery.openSettings}</Link>
            </div>
          </Card>

          {/* Section 4 — recommended communities (future module) */}
          <Card>
            <CardHeader eyebrow={t.dashboard.communities} title={t.dashboard.comingSoon} />
            <EmptyState icon={<Users size={23} />} title={t.empty.title} description={t.dashboard.suggestionsCopy} />
          </Card>

          {/* Section 5 — upcoming events (future module) */}
          <Card>
            <CardHeader eyebrow={t.dashboard.events} title={t.dashboard.comingSoon} />
            <EmptyState icon={<CalendarDays size={23} />} title={t.empty.title} description={t.dashboard.eventsCopy} />
          </Card>
        </div>
      </div>
    </section>
  )
}
