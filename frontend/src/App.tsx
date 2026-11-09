import { lazy, Suspense, type ReactNode } from 'react'
import { Navigate, Outlet, Route, Routes, useOutletContext } from 'react-router-dom'
import { Bell, CalendarDays, MessageCircle, Settings, ShieldCheck, Sparkles, Users } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { authService } from './services/authService'
import { AdminLayout, AuthLayout, MemberLayout, PublicLayout, ThemeSync } from './layouts/AppLayouts'
import { ToastViewport } from './components/ui'
import { DashboardPage } from './pages/DashboardPage'
import './App.css'
import './layout.css'
import './responsive.css'
import './features/discovery/discovery.css'
import './features/connections/connections.css'

const HomePage = lazy(() => import('./pages/PublicPages').then((module) => ({ default: module.HomePage })))
const PublicInfoPage = lazy(() => import('./pages/PublicPages').then((module) => ({ default: module.PublicInfoPage })))
const FeaturesPage = lazy(() => import('./pages/PublicPages').then((module) => ({ default: module.FeaturesPage })))
const PlaceholderPage = lazy(() => import('./pages/PlaceholderPage').then((module) => ({ default: module.PlaceholderPage })))
const LoginPage = lazy(() => import('./features/auth/AuthPages').then((module) => ({ default: module.LoginPage })))
const RegisterPage = lazy(() => import('./features/auth/AuthPages').then((module) => ({ default: module.RegisterPage })))
const ForgotPasswordPage = lazy(() => import('./features/auth/AuthPages').then((module) => ({ default: module.ForgotPasswordPage })))
const ResetPasswordPage = lazy(() => import('./features/auth/AuthPages').then((module) => ({ default: module.ResetPasswordPage })))
const VerifyEmailPage = lazy(() => import('./features/auth/AuthPages').then((module) => ({ default: module.VerifyEmailPage })))
const VerifyResultPage = lazy(() => import('./features/auth/AuthPages').then((module) => ({ default: module.VerifyResultPage })))
const ProfilePage = lazy(() => import('./features/profile/ProfilePage').then((module) => ({ default: module.ProfilePage })))
const SettingsPage = lazy(() => import('./features/settings/SettingsPage').then((module) => ({ default: module.SettingsPage })))
const DiscoverPage = lazy(() => import('./features/discovery/pages/DiscoverPage').then((module) => ({ default: module.DiscoverPage })))
const PeopleExplorerPage = lazy(() => import('./features/discovery/pages/PeopleExplorerPage').then((module) => ({ default: module.PeopleExplorerPage })))
const DiscoveryProfilePage = lazy(() => import('./features/discovery/pages/DiscoveryProfilePage').then((module) => ({ default: module.DiscoveryProfilePage })))
const DiscoverySettingsPage = lazy(() => import('./features/discovery/pages/DiscoverySettingsPage').then((module) => ({ default: module.DiscoverySettingsPage })))
const ConnectionsPage = lazy(() => import('./features/connections/pages/ConnectionsPage').then((module) => ({ default: module.ConnectionsPage })))
const NotificationsPage = lazy(() => import('./features/connections/pages/NotificationsPage').then((module) => ({ default: module.NotificationsPage })))
const AdminUsersPage = lazy(() => import('./features/admin/AdminPage').then((module) => ({ default: module.AdminUsersPage })))

function LoadingScreen() { return <div className="route-loading" role="status">Loading workspace…</div> }
function Lazy({ children }: { children: ReactNode }) { return <Suspense fallback={<LoadingScreen />}>{children}</Suspense> }

function ProtectedLayout({ roles }: { roles?: string[] }) {
  const query = useQuery({ queryKey: ['me'], queryFn: authService.me, retry: false })
  if (query.isLoading) return <LoadingScreen />
  if (query.error || !query.data) return <Navigate to="/login" replace />
  if (roles && !roles.includes(query.data.user.role)) return <PlaceholderPage title="Access restricted" description="Your account does not have permission to open this workspace." icon={ShieldCheck} />
  return <Outlet context={query.data.user} />
}

/** Guard nested admin routes by role. Renders inside ProtectedLayout. */
function RequireRoles({ roles }: { roles: string[] }) {
  const user = useOutletContext<import('./types').User>()
  if (!roles.includes(user.role)) return <PlaceholderPage title="Access restricted" description="Your account does not have permission to open this workspace." icon={ShieldCheck} />
  return <Outlet />
}

function MemberShell() { const user = useOutletContext<import('./types').User>(); return <MemberLayout user={user} /> }
function AdminShell() { const user = useOutletContext<import('./types').User>(); return <AdminLayout user={user} /> }

export default function App() {
  return <><ThemeSync /><ToastViewport /><Routes>
    <Route element={<PublicLayout />}>
      <Route path="/" element={<Lazy><HomePage /></Lazy>} />
      <Route path="/about" element={<Lazy><PublicInfoPage title="About Social Discovery" description="A platform for finding community through shared interests and moving at a human pace." /></Lazy>} />
      <Route path="/features" element={<Lazy><FeaturesPage /></Lazy>} />
      <Route path="/explore" element={<Lazy><PublicInfoPage title="Discover" description="Discovery will open up as people, interests, and communities become available." /></Lazy>} />
      <Route path="/community-guide" element={<Lazy><PublicInfoPage title="Communities" description="Community spaces will be introduced with clear guidelines and thoughtful moderation." /></Lazy>} />
      <Route path="/event-guide" element={<Lazy><PublicInfoPage title="Events" description="Events will help people gather around shared interests and experiences." /></Lazy>} />
      <Route path="/contact" element={<Lazy><PublicInfoPage title="Contact" description="A simple place to find support and share feedback." /></Lazy>} />
      <Route path="/privacy" element={<Lazy><PublicInfoPage title="Privacy Policy" description="Clear choices and respectful handling of personal data." /></Lazy>} />
      <Route path="/terms" element={<Lazy><PublicInfoPage title="Terms of Service" description="The shared expectations for participating here." /></Lazy>} />
      <Route path="/guidelines" element={<Lazy><PublicInfoPage title="Community Guidelines" description="Help make Social Discovery welcoming and safe." /></Lazy>} />
    </Route>
    <Route element={<AuthLayout />}>
      <Route path="/login" element={<Lazy><LoginPage /></Lazy>} />
      <Route path="/register" element={<Lazy><RegisterPage /></Lazy>} />
      <Route path="/forgot-password" element={<Lazy><ForgotPasswordPage /></Lazy>} />
      <Route path="/reset-password" element={<Lazy><ResetPasswordPage /></Lazy>} />
      <Route path="/verify-email" element={<Lazy><VerifyEmailPage /></Lazy>} />
      <Route path="/verify-email/result" element={<Lazy><VerifyResultPage /></Lazy>} />
    </Route>
    <Route element={<ProtectedLayout />}>
      <Route element={<MemberShell />}>
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/discover" element={<Lazy><DiscoverPage /></Lazy>} />
        <Route path="/people" element={<Lazy><PeopleExplorerPage /></Lazy>} />
        <Route path="/people/:username" element={<Lazy><DiscoveryProfilePage /></Lazy>} />
        <Route path="/connections" element={<Lazy><ConnectionsPage /></Lazy>} />
        <Route path="/feed" element={<Lazy><PlaceholderPage title="Feed" description="A future home for updates from your communities." icon={Sparkles} /></Lazy>} />
        <Route path="/messages" element={<Lazy><PlaceholderPage title="Messages" description="Private conversations are part of the next layer." icon={MessageCircle} /></Lazy>} />
        <Route path="/communities" element={<Lazy><PlaceholderPage title="Communities" description="Find spaces organized around the things you care about." icon={Users} /></Lazy>} />
        <Route path="/events" element={<Lazy><PlaceholderPage title="Events" description="Join gatherings, workshops, and shared experiences." icon={CalendarDays} /></Lazy>} />
        <Route path="/notifications" element={<Lazy><NotificationsPage /></Lazy>} />
        <Route path="/profile" element={<Lazy><ProfilePage /></Lazy>} />
        <Route path="/settings" element={<Lazy><SettingsPage /></Lazy>} />
        <Route path="/settings/discovery" element={<Lazy><DiscoverySettingsPage /></Lazy>} />
      </Route>
    </Route>
    {/* Administration shell: role-aware navigation, protected routes. */}
    <Route element={<ProtectedLayout roles={['moderator', 'super_admin']} />}>
      <Route path="/admin" element={<AdminShell />}>
        <Route index element={<Navigate to="overview" replace />} />
        <Route path="overview" element={<Lazy><PlaceholderPage title="Admin overview" description="A protected workspace for platform health and stewardship." icon={ShieldCheck} /></Lazy>} />
        <Route path="reports" element={<Lazy><PlaceholderPage title="Reports" description="Review reports and record accountable outcomes." icon={Bell} /></Lazy>} />
        <Route path="moderation" element={<Lazy><PlaceholderPage title="Content moderation" description="A protected workspace for community care and safety." icon={ShieldCheck} /></Lazy>} />
        <Route element={<RequireRoles roles={['super_admin']} />}>
          <Route path="users" element={<Lazy><AdminUsersPage /></Lazy>} />
          <Route path="moderators" element={<Lazy><PlaceholderPage title="Moderator management" description="Trust and access are granted deliberately; moderator management arrives with the moderation layer." icon={Users} /></Lazy>} />
          <Route path="analytics" element={<Lazy><PlaceholderPage title="Analytics" description="Platform analytics will give stewards a careful view of healthy activity." icon={Sparkles} /></Lazy>} />
          <Route path="settings" element={<Lazy><PlaceholderPage title="Platform settings" description="Manage system defaults and operational controls." icon={Settings} /></Lazy>} />
          <Route path="security-logs" element={<Lazy><PlaceholderPage title="Security logs" description="A responsible record of sensitive account events and administrative access." icon={Bell} /></Lazy>} />
        </Route>
      </Route>
    </Route>
    <Route element={<ProtectedLayout roles={['moderator', 'super_admin']} />}><Route path="/moderator/overview" element={<Lazy><PlaceholderPage title="Moderation workspace" description="A restricted workspace for reviewing community reports." icon={ShieldCheck} /></Lazy>} /></Route>
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes></>
}
