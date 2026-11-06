import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { Bell, CalendarDays, ChevronDown, Compass, HeartHandshake, Home, Languages, LogOut, Menu, MessageCircle, Moon, Settings, ShieldCheck, Sparkles, Sun, Users, X } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import type { User } from '../types'
import { copy, type Locale } from '../i18n'
import { authService } from '../services/authService'
import { useUiStore } from '../stores/uiStore'
import { Avatar, Dropdown, IconButton, SearchBar } from '../components/ui'

const memberGroups = [
  { label: 'main', items: [['dashboard', '/dashboard', Home], ['discover', '/discover', Compass], ['people', '/people', Users], ['connections', '/connections', HeartHandshake], ['feed', '/feed', Sparkles]] },
  { label: 'social', items: [['messages', '/messages', MessageCircle], ['communities', '/communities', Users], ['events', '/events', CalendarDays], ['notifications', '/notifications', Bell]] },
  { label: 'account', items: [['profile', '/profile', Users], ['settings', '/settings', Settings]] },
] as const
const adminItems = [['overview', '/admin/overview', Home], ['users', '/admin/users', Users], ['moderation', '/admin/moderation', ShieldCheck], ['reports', '/admin/reports', Bell], ['settings', '/admin/settings', Settings]] as const

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
  return <Dropdown label={<><Languages size={16} /><span className="language-label">{locale === 'en' ? 'EN' : 'MM'}</span></>}><button className="menu-item" onClick={() => setLocale('en')}><span>English</span>{locale === 'en' && <span>✓</span>}</button><button className="menu-item" onClick={() => setLocale('my')}><span>မြန်မာ</span>{locale === 'my' && <span>✓</span>}</button><span className="sr-only">{t.actions.language}</span></Dropdown>
}

function ThemeToggle() {
  const theme = useUiStore((state) => state.theme); const setTheme = useUiStore((state) => state.setTheme)
  const next = theme === 'light' ? 'dark' : theme === 'dark' ? 'system' : 'light'; const Icon = theme === 'dark' ? Moon : theme === 'system' ? Sun : Sun
  return <IconButton label={`Theme: ${theme}. Change theme`} onClick={() => setTheme(next)}><Icon size={18} /></IconButton>
}

function Logo({ compact = false }: { compact?: boolean }) { return <Link className="brand" to="/dashboard" aria-label="Social Discovery"><span className="brand-mark"><Sparkles size={17} /></span>{!compact && <span>Social Discovery</span>}</Link> }

export function PublicLayout() {
  const { t } = useTranslation(); const [mobileOpen, setMobileOpen] = useState(false)
  const publicLinks = [[t.public.home, '/'], [t.public.features, '/features'], [t.public.discover, '/explore'], [t.public.communities, '/community-guide'], [t.public.about, '/about'], [t.public.contact, '/contact']] as const
  return <div className="public-layout"><header className="public-header"><Logo compact={false} /><nav className={mobileOpen ? 'public-nav is-open' : 'public-nav'} aria-label="Public navigation">{publicLinks.map(([label, path]) => <NavLink key={path} to={path} end={path === '/'} onClick={() => setMobileOpen(false)}>{label}</NavLink>)}<div className="public-nav-tools"><LanguageToggle /><ThemeToggle /><Link className="text-button" to="/login">{t.actions.signIn}</Link><Link className="ui-button ui-button-primary ui-button-sm" to="/register">{t.actions.getStarted}</Link></div></nav><IconButton className="public-mobile-trigger" label={mobileOpen ? t.actions.close : t.actions.menu} onClick={() => setMobileOpen((value) => !value)}>{mobileOpen ? <X size={20} /> : <Menu size={20} />}</IconButton></header><main>{<Outlet />}</main><PublicFooter /></div>
}

export function PublicFooter() {
  const { t } = useTranslation(); const groups = [[t.public.home, [['Home', '/'], ['About', '/about'], ['Features', '/features'], ['Discover', '/explore']]], [t.public.communities, [['Communities', '/community-guide'], ['Events', '/event-guide'], ['Community Guidelines', '/guidelines'], ['Safety Center', '/guidelines']]], [t.public.help, [['Help Center', '/contact'], ['Contact', '/contact'], ['Report a Problem', '/contact']]], ['Legal', [['Privacy Policy', '/privacy'], ['Terms of Service', '/terms'], ['Cookie Policy', '/privacy']]]] as const
  return <footer className="public-footer"><div className="footer-main"><div className="footer-brand"><Logo /><p>{t.public.footerTagline}</p></div>{groups.map(([title, links]) => <div key={title} className="footer-column"><h3>{title}</h3>{links.map(([label, path]) => <Link key={path + label} to={path}>{label}</Link>)}</div>)}</div><div className="footer-bottom"><span>© {new Date().getFullYear()} Social Discovery</span><span>{t.public.footerTagline}</span></div></footer>
}

export function AuthLayout() {
  const { t } = useTranslation()
  return <div className="auth-layout"><aside className="auth-brand-panel"><Logo /><div className="auth-brand-copy"><p className="eyebrow">{t.nav}</p><h1>{localeText(t.locale, 'Meet people around what matters to you.', 'သင့်အတွက် အရေးကြီးသောအရာများမှတစ်ဆင့် လူများနှင့်တွေ့ဆုံပါ။')}</h1><p>{localeText(t.locale, 'Discover communities, conversations, and experiences that feel like yours.', 'သင့်နှင့်သက်ဆိုင်သော အသိုင်းအဝိုင်းများ၊ စကားဝိုင်းများနှင့် အတွေ့အကြုံများကို ရှာဖွေပါ။')}</p></div><span className="auth-brand-note">{localeText(t.locale, 'Private by design · Human at heart', 'ကိုယ်ရေးလုံခြုံမှုနှင့် လူသားဆန်မှု')}</span></aside><main className="auth-content"><Outlet /></main></div>
}

function localeText(locale: Locale, english: string, myanmar: string) { return locale === 'my' ? myanmar : english }

function MemberSidebar({ user, mobileOpen, onClose }: { user: User; mobileOpen: boolean; onClose: () => void }) {
  const { t } = useTranslation(); const sidebarOpen = useUiStore((state) => state.sidebarOpen); const toggleSidebar = useUiStore((state) => state.toggleSidebar); const queryClient = useQueryClient(); const navigate = useNavigate()
  const logout = async () => { await authService.logout(); queryClient.clear(); navigate('/login') }
  return <aside className={`member-sidebar ${sidebarOpen ? 'is-expanded' : 'is-collapsed'} ${mobileOpen ? 'is-mobile-open' : ''}`}><div className="sidebar-header"><Logo compact={!sidebarOpen} /><IconButton className="mobile-only" label={t.actions.close} onClick={onClose}><X size={19} /></IconButton></div><nav className="member-nav" aria-label="Member navigation">{memberGroups.map((group) => <div className="nav-group" key={group.label}><p className="nav-group-label">{t.nav[group.label as keyof typeof t.nav]}</p>{group.items.map(([key, path, Icon]) => <NavLink key={path} to={path} end={path === '/dashboard'} title={!sidebarOpen ? t.nav[key as keyof typeof t.nav] : undefined} onClick={onClose} className={({ isActive }) => isActive ? 'is-active' : ''}><Icon size={18} aria-hidden="true" /><span>{t.nav[key as keyof typeof t.nav]}</span></NavLink>)}</div>)}</nav><div className="sidebar-account"><div className="sidebar-user"><Avatar name={user.name} size="sm" /><span><strong>{user.name}</strong><small>@{user.username}</small></span></div><div className="sidebar-account-actions"><IconButton label={t.actions.signOut} onClick={() => void logout}><LogOut size={17} /></IconButton><IconButton className="desktop-only" label={t.actions.toggleSidebar} onClick={toggleSidebar}><Menu size={17} /></IconButton></div></div></aside>
}

function MemberTopbar({ user, onOpenMenu }: { user: User; onOpenMenu: () => void }) {
  const { t } = useTranslation(); const navigate = useNavigate(); const [search, setSearch] = useState(''); const queryClient = useQueryClient()
  const logout = async () => { await authService.logout(); queryClient.clear(); navigate('/login') }
  return <header className="member-topbar"><IconButton className="mobile-only" label={t.actions.menu} onClick={onOpenMenu}><Menu size={20} /></IconButton><SearchBar value={search} onChange={setSearch} onClear={() => setSearch('')} placeholder={localeSearch(t.locale)} className="topbar-search" /><div className="topbar-actions"><IconButton label="Messages" onClick={() => navigate('/messages')}><MessageCircle size={19} /></IconButton><IconButton label="Notifications" onClick={() => navigate('/notifications')}><Bell size={19} /></IconButton><LanguageToggle /><ThemeToggle /><Dropdown label={<><Avatar name={user.name} size="sm" /><span className="profile-label">{user.name}</span></>}><Link className="menu-item" to="/profile"><Users size={16} />{t.nav.profile}</Link><Link className="menu-item" to="/settings"><Settings size={16} />{t.nav.settings}</Link><button className="menu-item menu-item-danger" onClick={() => void logout}><LogOut size={16} />{t.actions.signOut}</button></Dropdown></div></header>
}

function localeSearch(locale: Locale) { return locale === 'my' ? 'လူများ၊ အသိုင်းအဝိုင်းများ သို့မဟုတ် ပွဲများကို ရှာဖွေပါ' : 'Search people, communities, or events' }

export function MemberLayout({ user }: { user: User }) {
  const [mobileOpen, setMobileOpen] = useState(false)
  return <div className="member-shell"><MemberSidebar user={user} mobileOpen={mobileOpen} onClose={() => setMobileOpen(false)} /><div className="member-content"><MemberTopbar user={user} onOpenMenu={() => setMobileOpen(true)} /><main className="workspace"><Outlet /></main><nav className="mobile-bottom-nav" aria-label="Primary navigation"><NavLink to="/dashboard"><Home size={18} /><span>Home</span></NavLink><NavLink to="/discover"><Compass size={18} /><span>Discover</span></NavLink><NavLink to="/messages"><MessageCircle size={18} /><span>Messages</span></NavLink><NavLink to="/profile"><Users size={18} /><span>Profile</span></NavLink></nav></div></div>
}

export function AdminLayout({ user }: { user: User }) {
  const { t } = useTranslation(); const navigate = useNavigate(); const queryClient = useQueryClient(); const [mobileOpen, setMobileOpen] = useState(false)
  const logout = async () => { await authService.logout(); queryClient.clear(); navigate('/login') }
  return <div className="admin-shell"><aside className={`admin-sidebar ${mobileOpen ? 'is-mobile-open' : ''}`}><div className="sidebar-header"><Logo /><IconButton className="mobile-only" label={t.actions.close} onClick={() => setMobileOpen(false)}><X size={19} /></IconButton></div><div className="admin-badge"><ShieldCheck size={16} /> Administration</div><nav className="admin-nav" aria-label="Administration navigation">{adminItems.map(([key, path, Icon]) => <NavLink key={path} to={path} onClick={() => setMobileOpen(false)} className={({ isActive }) => isActive ? 'is-active' : ''}><Icon size={17} />{key === 'overview' ? 'Overview' : key === 'users' ? 'User management' : key === 'moderation' ? 'Content moderation' : key === 'reports' ? 'Reports' : 'Platform settings'}</NavLink>)}</nav><div className="sidebar-account"><div className="sidebar-user"><Avatar name={user.name} size="sm" /><span><strong>{user.name}</strong><small>{user.role}</small></span></div><IconButton label={t.actions.signOut} onClick={() => void logout}><LogOut size={17} /></IconButton></div></aside><div className="admin-content"><header className="admin-topbar"><IconButton className="mobile-only" label={t.actions.menu} onClick={() => setMobileOpen(true)}><Menu size={20} /></IconButton><div><p className="eyebrow">Protected workspace</p><h1>Administration</h1></div><div className="topbar-actions"><ThemeToggle /><Dropdown label={<><Avatar name={user.name} size="sm" /><ChevronDown size={15} /></>}><Link className="menu-item" to="/profile">My profile</Link><button className="menu-item menu-item-danger" onClick={() => void logout}>Sign out</button></Dropdown></div></header><main className="workspace"><Outlet /></main></div></div>
}
