import { CalendarDays, HeartHandshake, Sparkles, Users } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { authService } from '../services/authService'
import { profileService } from '../services/profileService'
import { useTranslation } from '../layouts/AppLayouts'
import { Avatar, Card, CardHeader, EmptyState } from '../components/ui'

const hourOf = (date: Date) => date.getHours()

function useGreeting(): 'greeting' | 'greetingAfternoon' | 'greetingEvening' {
  const [hour] = useState(() => hourOf(new Date()))
  return hour < 12 ? 'greeting' : hour < 18 ? 'greetingAfternoon' : 'greetingEvening'
}

export function DashboardPage() {
  const { t, locale } = useTranslation(); const greetingKey = useGreeting(); const user = useQuery({ queryKey: ['me'], queryFn: authService.me, staleTime: 60_000 }); const profile = useQuery({ queryKey: ['profile'], queryFn: profileService.get, retry: false }); const firstName = user.data?.user.name.split(' ')[0] ?? 'there'; const completion = profile.data ? Math.min(100, Math.round(([profile.data.display_name, profile.data.bio, profile.data.location, profile.data.avatar_url, profile.data.interests?.length ? 'interests' : ''].filter(Boolean).length / 5) * 100)) : 0
  const shortcuts = [{ icon: Users, title: t.dashboard.people, path: '/people' }, { icon: Sparkles, title: t.dashboard.interests, path: '/discover' }, { icon: HeartHandshake, title: t.dashboard.communities, path: '/communities' }, { icon: CalendarDays, title: t.dashboard.events, path: '/events' }]
  return <section className="page-shell dashboard-page"><div className="dashboard-welcome"><div><p className="eyebrow">{t.dashboard.eyebrow}</p><h1>{t.dashboard[greetingKey]}, {firstName}.</h1><p className="page-description">{t.dashboard.intro}</p></div><Avatar name={user.data?.user.name ?? firstName} src={profile.data?.avatar_url} size="xl" /></div><Card className="dashboard-shortcuts"><CardHeader eyebrow={t.dashboard.shortcuts} title={t.dashboard.shortcutsCopy} /> <div className="shortcut-grid">{shortcuts.map(({ icon: Icon, title, path }) => <Link key={path} className="shortcut-card" to={path}><span className="shortcut-icon"><Icon size={18} /></span><span><strong>{title}</strong><small>{locale === 'my' ? 'စတင်ရန် ရွေးပါ' : 'Explore the next layer'}</small></span><span className="shortcut-arrow">→</span></Link>)}</div></Card><div className="dashboard-columns"><div className="dashboard-main-column"><Card><CardHeader eyebrow={t.dashboard.suggestions} title={t.dashboard.suggestionsCopy} /><EmptyState icon={<Users size={23} />} title={t.empty.title} description={t.dashboard.suggestionsCopy} /></Card><Card><CardHeader eyebrow={t.dashboard.activity} title={t.dashboard.activityCopy} /><EmptyState icon={<Sparkles size={23} />} title={t.empty.title} description={t.dashboard.activityCopy} /></Card></div><div className="dashboard-side-column"><Card className="profile-progress-card"><CardHeader eyebrow={t.dashboard.profile} title={`${completion}%`} /><div className="progress-track"><span style={{ width: `${completion}%` }} /></div><p>{t.dashboard.profileCopy}</p><Link className="ui-button ui-button-soft ui-button-sm" to="/profile">{t.dashboard.editProfile}</Link></Card><Card><CardHeader eyebrow={t.dashboard.communities} title={t.dashboard.comingSoon} /><EmptyState icon={<Users size={23} />} title={t.empty.title} description={t.dashboard.suggestionsCopy} /></Card><Card><CardHeader eyebrow={t.dashboard.events} title={t.dashboard.comingSoon} /><EmptyState icon={<CalendarDays size={23} />} title={t.empty.title} description={t.dashboard.eventsCopy} /></Card></div></div></section>
}
