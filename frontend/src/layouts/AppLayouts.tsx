import { useEffect, useRef, useState, type ComponentType, type ReactNode } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { Bell, CalendarDays, ChevronDown, Compass, HeartHandshake, Home, Languages, LockKeyhole, LogOut, Menu, MessageCircle, Moon, PanelLeftClose, PanelLeftOpen, Settings, ShieldCheck, Sparkles, Sun, Users, X } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import type { User } from '../types'
import { copy, interpolate, type Copy, type Locale } from '../i18n'
import { authService } from '../services/authService'
import { useUiStore } from '../stores/uiStore'
import { Avatar, Dropdown, IconButton, SearchBar, Tooltip } from '../components/ui'

type NavEntry = { key: keyof Copy['nav']; path: string; Icon: ComponentType<{ size?: number }> }
type AdminEntry = { key: keyof Copy['admin'] | 'overview' | 'userManagement' | 'reports' | 'contentModeration'; path: string; Icon: ComponentType<{ size?: number }>; roles: readonly ['moderator', 'super_admin'] | readonly ['super_admin'] }

const memberGroups: { label: keyof Copy['nav']; items: readonly NavEntry[] }[] = [
  { label: 'main', items: [
    { key: 'dashboard', path: '/dashboard', Icon: Home }, { key: 'discover', path: '/discover', Icon: Compass }, { key: 'people', path: '/people', Icon: Users }, { key: 'connections', path: '/connections', Icon: HeartHandshake }, { key: 'feed', path: '/feed', Icon: Sparkles },
  ] },
  { label: 'social', items: [
    { key: 'messages', path: '/messages', Icon: MessageCircle }, { key: 'communities', path: '/communities', Icon: Users }, { key: 'events', path: '/events', Icon: CalendarDays }, { key: 'notifications', path: '/notifications', Icon: Bell },
  ] },
  { label: 'account', items: [
    { key: 'profile', path: '/profile', Icon: Users }, { key: 'settings', path: '/settings', Icon: Settings },
  ] },
]

/** Role-aware administration navigation. `superAdminOnly` items render only for super_admin accounts. */
const superAdminOnly = ['super_admin'] as const
const adminItems: readonly AdminEntry[] = [
  { key: 'overview', path: '/admin/overview', Icon: Home, roles: ['moderator', 'super_admin'] },
  { key: 'userManagement', path: '/admin/users', Icon: Users, roles: superAdminOnly },
  { key: 'moderatorManagement', path: '/admin/moderators', Icon: ShieldCheck, roles: superAdminOnly },
  { key: 'reports', path: '/admin/reports', Icon: Bell, roles: ['moderator', 'super_admin'] },
  { key: 'contentModeration', path: '/admin/moderation', Icon: ShieldCheck, roles: ['moderator', 'super_admin'] },
  { key: 'analytics', path: '/admin/analytics', Icon: Compass, roles: superAdminOnly },
  { key: 'platformSettings', path: '/admin/settings', Icon: Settings, roles: superAdminOnly },
  { key: 'securityLogs', path: '/admin/security-logs', Icon: LockKeyhole, roles: superAdminOnly },
]


export function useTranslation() {
  const locale = useUiStore((state) => state.locale)
  return { locale, t: copy[locale] }
}

export function ThemeSync() {
  const theme = useUiStore((state) => state.theme)
  const locale = useUiStore((state) => state.locale)
  useEffect(() => {
    const apply = () => { const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches); document.documentElement.dataset.theme = dark ? 'dark' : 'light'; document.documentElement.lang = locale === 'my' ? 'my' : 'en' }
    apply()
    if (theme !== 'system') return
    const media = window.matchMedia('(prefers-color-scheme: dark)'); media.addEventListener('change', apply); return () => media.removeEventListener('change', apply)
  }, [theme, locale])
  return null
}

function LanguageToggle() {
  const { locale, t } = useTranslation(); const setLocale = useUiStore((state) => state.setLocale)
  return <Dropdown label={<><Languages size={16} /><span className="language-label">{locale === 'en' ? 'EN' : 'MM'}</span></>}><button className="menu-item" onClick={() => setLocale('en')}><span>English</span>{locale === 'en' && <span aria-hidden="true">✓</span>}</button><button className="menu-item" onClick={() => setLocale('my')}><span>မြန်မာ</span>{locale === 'my' && <span aria-hidden="true">✓</span>}</button><span className="sr-only">{t.actions.language}</span></Dropdown>
}

function ThemeToggle() {
  const { t } = useTranslation()
  const theme = useUiStore((state) => state.theme); const setTheme = useUiStore((state) => state.setTheme)
  const next = theme === 'light' ? 'dark' : theme === 'dark' ? 'system' : 'light'; const Icon = theme === 'dark' ? Moon : Sun
  return <IconButton label={interpolate(`Theme: ${theme}. Change theme` === '' ? '' : t.themeLabel, { mode: t.settings[theme] ?? theme })} onClick={() => setTheme(next)}><Icon size={18} /></IconButton>
}

function Logo({ compact = false }: { compact?: boolean }) { return <Link className="brand" to="/dashboard" aria-label="Social Discovery"><span className="brand-mark"><Sparkles size={17} /></span>{!compact && <span>Social Discovery</span>}</Link> }

export function useLogout() {
  const navigate = useNavigate(); const queryClient = useQueryClient()
  return async () => { await authService.logout(); queryClient.clear(); navigate('/login') }
}

export function PublicLayout() {
  const { t } = useTranslation(); const [mobileOpen, setMobileOpen] = useState(false)
  const publicLinks: readonly [string, string][] = [[t.public.home, '/'], [t.public.features, '/features'], [t.public.discover, '/explore'], [t.public.communities, '/community-guide'], [t.public.about, '/about'], [t.public.contact, '/contact']]
  return <div className="public-layout"><header className="public-header"><Logo compact={false} /><nav className={mobileOpen ? 'public-nav is-open' : 'public-nav'} aria-label="Public navigation">{publicLinks.map(([label, path]) => <NavLink key={path} to={path} end={path === '/'} onClick={() => setMobileOpen(false)}>{label}</NavLink>)}<div className="public-nav-tools"><LanguageToggle /><ThemeToggle /><Link className="text-button" to="/login">{t.actions.signIn}</Link><Link className="ui-button ui-button-primary ui-button-sm" to="/register">{t.actions.getStarted}</Link></div></nav><IconButton className="public-mobile-trigger" label={mobileOpen ? t.actions.close : t.actions.menu} onClick={() => setMobileOpen((value) => !value)}>{mobileOpen ? <X size={20} /> : <Menu size={20} />}</IconButton></header><main><Outlet /></main><PublicFooter /></div>
}

/** Optional social links are configuration, not invented accounts. Set VITE_SOCIAL_LINKS='[{"label":"X","url":"https://…"}]' to enable. */
function SocialLinks() {
  const { t } = useTranslation()
  let links: { label: string; url: string }[] = []
  try { const raw = (import.meta.env.VITE_SOCIAL_LINKS as string | undefined) ?? ''; if (raw) links = JSON.parse(raw) } catch { links = [] }
  if (!links.length) return null
  return <div className="footer-column"><h3>{t.footer.followUs}</h3>{links.map((link) => <a key={link.url} href={link.url} target="_blank" rel="noreferrer noopener">{link.label}</a>)}</div>
}

const currentYear = new Date().getFullYear()

export function PublicFooter() {
  const { t } = useTranslation()
  const groups: readonly [string, readonly [string, string][]][] = [
    [t.footer.platform, [[t.public.home, '/'], [t.public.about, '/about'], [t.public.features, '/features'], [t.public.discover, '/explore']]],
    [t.footer.community, [[t.public.communities, '/community-guide'], [t.nav.events, '/event-guide'], [t.public.guidelines, '/guidelines'], [t.public.safety, '/guidelines']]],
    [t.footer.support, [[t.public.help, '/contact'], [t.public.contact, '/contact'], [t.public.reportProblem, '/contact']]],
    [t.footer.legal, [[t.public.privacy, '/privacy'], [t.public.terms, '/terms'], [t.public.cookies, '/privacy']]],
  ]
  return <footer className="public-footer"><div className="footer-main"><div className="footer-brand"><Logo /><p>{t.public.footerTagline}</p></div>{groups.map(([title, links]) => <div key={title} className="footer-column"><h3>{title}</h3>{links.map(([label, path]) => <Link key={path + label} to={path}>{label}</Link>)}</div>)}<SocialLinks /></div><div className="footer-bottom"><span>© {currentYear} Social Discovery · {t.public.copyright}</span><span>{t.public.footerTagline}</span></div></footer>
}

export function AuthLayout() {
  const { t, locale } = useTranslation()
  return <div className="auth-layout"><aside className="auth-brand-panel"><Logo /><div className="auth-brand-copy"><p className="eyebrow">{t.brandLine}</p><h1>{localeText(locale, 'Meet people around what matters to you.', 'သင့်အတွက် အရေးကြီးသောအရာများမှတစ်ဆင့် လူများနှင့်တွေ့ဆုံပါ။')}</h1><p>{localeText(locale, 'Discover communities, conversations, and experiences that feel like yours.', 'သင့်နှင့်သက်ဆိုင်သော အသိုင်းအဝိုင်းများ၊ စကားဝိုင်းများနှင့် အတွေ့အကြုံများကို ရှာဖွေပါ။')}</p></div><span className="auth-brand-note">{localeText(locale, 'Private by design · Human at heart', 'ကိုယ်ရေးလုံခြုံမှုနှင့် လူသားဆန်မှု')}</span></aside><main className="auth-content"><Outlet /></main></div>
}

function localeText(locale: Locale, english: string, myanmar: string) { return locale === 'my' ? myanmar : english }

/** Sidebar navigation row: horizontal icon + label, active-route state, tooltip when collapsed. */
function SidebarLink({ to, end = false, label, collapsed, onClick, children }: { to: string; end?: boolean; label: string; collapsed: boolean; onClick?: () => void; children: ReactNode }) {
  const linkRef = useRef<HTMLAnchorElement>(null)
  return (
    <NavLink ref={linkRef} to={to} end={end} onClick={onClick} className={({ isActive }) => (isActive ? 'is-active' : undefined)} aria-label={collapsed ? label : undefined}>
      {children}
      <span>{label}</span>
      {collapsed ? <span className="sr-only">{label}</span> : null}
    </NavLink>
  )
}

function MemberSidebar({ user, mobileOpen, onClose }: { user: User; mobileOpen: boolean; onClose: () => void }) {
  const { t } = useTranslation(); const sidebarOpen = useUiStore((state) => state.sidebarOpen); const toggleSidebar = useUiStore((state) => state.toggleSidebar); const logout = useLogout()
  const CollapseIcon = sidebarOpen ? PanelLeftClose : PanelLeftOpen
  return <aside className={`member-sidebar ${sidebarOpen ? 'is-expanded' : 'is-collapsed'} ${mobileOpen ? 'is-mobile-open' : ''}`}><div className="sidebar-header"><Logo compact={!sidebarOpen} /><span className="sidebar-header-tools"><IconButton className="mobile-only" label={t.actions.close} onClick={onClose}><X size={19} /></IconButton><Tooltip content={t.actions.toggleSidebar} position="right"><IconButton className="desktop-only" label={t.actions.toggleSidebar} onClick={toggleSidebar}><CollapseIcon size={17} /></IconButton></Tooltip></span></div><nav className="member-nav" aria-label="Member navigation">{memberGroups.map((group) => <div className="nav-group" key={group.label}><p className="nav-group-label">{t.nav[group.label]}</p>{group.items.map(({ key, path, Icon }) => <SidebarLink key={path} to={path} end={path === '/dashboard'} label={t.nav[key]} collapsed={!sidebarOpen} onClick={onClose}><Icon size={18} aria-hidden="true" /></SidebarLink>)}</div>)}</nav><div className="sidebar-account"><div className="sidebar-user"><Avatar name={user.name} size="sm" /><span><strong>{user.name}</strong><small>@{user.username}</small></span></div><div className="sidebar-account-actions"><IconButton label={t.actions.signOut} onClick={() => void logout}><LogOut size={17} /></IconButton></div></div></aside>
}

const memberTopbarTitles: [RegExp, keyof Copy['nav']][] = [
  [/^\/dashboard/, 'dashboard'],
  [/^\/discover/, 'discover'],
  [/^\/people\/.+/, 'profile'],
  [/^\/people/, 'people'],
  [/^\/connections/, 'connections'],
  [/^\/feed/, 'feed'],
  [/^\/messages/, 'messages'],
  [/^\/communities/, 'communities'],
  [/^\/events/, 'events'],
  [/^\/notifications/, 'notifications'],
  [/^\/settings\/discovery/, 'settings'],
  [/^\/settings/, 'settings'],
  [/^\/profile/, 'profile'],
]

/** Route → current page title for the topbar. */
export function usePageTitle(): keyof Copy['nav'] {
  const { pathname } = useLocation()
  const matched = memberTopbarTitles.find(([pattern]) => pattern.test(pathname))
  return matched?.[1] ?? 'dashboard'
}

function MemberTopbar({ user, onOpenMenu }: { user: User; onOpenMenu: () => void }) {
  const { t } = useTranslation(); const navigate = useNavigate(); const [search, setSearch] = useState(''); const logout = useLogout(); const toggleSidebar = useUiStore((state) => state.toggleSidebar)
  const pageTitle = usePageTitle()
  return <header className="member-topbar"><div className="topbar-lead"><IconButton className="mobile-only" label={t.actions.menu} onClick={onOpenMenu}><Menu size={20} /></IconButton><IconButton className="desktop-only" label={t.actions.toggleSidebar} onClick={toggleSidebar}><Menu size={18} /></IconButton><h1 className="topbar-title">{t.nav[pageTitle]}</h1></div><SearchBar value={search} onChange={setSearch} onClear={() => setSearch('')} placeholder={t.searchPlaceholder} className="topbar-search" /><div className="topbar-actions"><IconButton label={t.nav.messages} onClick={() => navigate('/messages')}><MessageCircle size={19} /></IconButton><IconButton label={t.nav.notifications} onClick={() => navigate('/notifications')}><Bell size={19} /></IconButton><LanguageToggle /><ThemeToggle /><Dropdown label={<><Avatar name={user.name} size="sm" /><span className="profile-label">{user.name}</span></>}><Link className="menu-item" to="/profile"><Users size={16} />{t.nav.profile}</Link><Link className="menu-item" to="/settings"><Settings size={16} />{t.nav.settings}</Link><button className="menu-item menu-item-danger" onClick={() => void logout}><LogOut size={16} />{t.actions.signOut}</button></Dropdown></div></header>
}

export function MemberLayout({ user }: { user: User }) {
  const { t } = useTranslation(); const [mobileOpen, setMobileOpen] = useState(false)
  return <div className="member-shell"><MemberSidebar user={user} mobileOpen={mobileOpen} onClose={() => setMobileOpen(false)} /><div className="member-content"><MemberTopbar user={user} onOpenMenu={() => setMobileOpen(true)} /><main className="workspace"><Outlet /></main><nav className="mobile-bottom-nav" aria-label="Primary navigation"><NavLink to="/dashboard"><Home size={18} /><span>{t.nav.dashboard}</span></NavLink><NavLink to="/discover"><Compass size={18} /><span>{t.nav.discover}</span></NavLink><NavLink to="/messages"><MessageCircle size={18} /><span>{t.nav.messages}</span></NavLink><NavLink to="/profile"><Users size={18} /><span>{t.nav.profile}</span></NavLink></nav></div></div>
}

export function AdminLayout({ user }: { user: User }) {
  const { t } = useTranslation(); const [mobileOpen, setMobileOpen] = useState(false); const logout = useLogout()
  const visibleItems = adminItems.filter((item) => (item.roles as readonly string[]).includes(user.role))
  return <div className="admin-shell"><aside className={`admin-sidebar ${mobileOpen ? 'is-mobile-open' : ''}`}><div className="sidebar-header"><Logo /><IconButton className="mobile-only" label={t.actions.close} onClick={() => setMobileOpen(false)}><X size={19} /></IconButton></div><div className="admin-badge"><ShieldCheck size={16} /> {t.admin.badge}</div><nav className="admin-nav" aria-label="Administration navigation">{visibleItems.map(({ key, path, Icon }) => <NavLink key={path} to={path} onClick={() => setMobileOpen(false)} className={({ isActive }) => isActive ? 'is-active' : ''}><Icon size={17} />{t.admin[key]}</NavLink>)}</nav><div className="sidebar-account"><div className="sidebar-user"><Avatar name={user.name} size="sm" /><span><strong>{user.name}</strong><small>{t.roles[user.role as keyof typeof t.roles] ?? user.role}</small></span></div><IconButton label={t.actions.signOut} onClick={() => void logout}><LogOut size={17} /></IconButton></div></aside><div className="admin-content"><header className="admin-topbar"><IconButton className="mobile-only" label={t.actions.menu} onClick={() => setMobileOpen(true)}><Menu size={20} /></IconButton><div><p className="eyebrow">{t.admin.eyebrow}</p><h1>{t.admin.title}</h1></div><div className="topbar-actions"><ThemeToggle /><Dropdown label={<><Avatar name={user.name} size="sm" /><ChevronDown size={15} /></>}><Link className="menu-item" to="/profile"><Users size={16} />{t.nav.profile}</Link><button className="menu-item menu-item-danger" onClick={() => void logout}><LogOut size={16} />{t.actions.signOut}</button></Dropdown></div></header><main className="workspace"><Outlet /></main></div></div>
}
