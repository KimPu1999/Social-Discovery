import { lazy, Suspense, type ReactNode } from 'react'
import { Navigate, Outlet, Route, Routes, useOutletContext } from 'react-router-dom'
import { Bell, CalendarDays, Compass, HeartHandshake, MessageCircle, Settings, ShieldCheck, Sparkles, Users } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { authService } from './services/authService'
import { AdminLayout, AuthLayout, MemberLayout, PublicLayout, ThemeSync } from './layouts/AppLayouts'
import { HomePage, PublicInfoPage } from './pages/PublicPages'
import { DashboardPage } from './pages/DashboardPage'
import { PlaceholderPage } from './pages/PlaceholderPage'
import './App.css'
import './responsive.css'

const LoginPage = lazy(() => import('./features/auth/AuthPages').then((module) => ({ default: module.LoginPage })))
const RegisterPage = lazy(() => import('./features/auth/AuthPages').then((module) => ({ default: module.RegisterPage })))
const ForgotPasswordPage = lazy(() => import('./features/auth/AuthPages').then((module) => ({ default: module.ForgotPasswordPage })))
const ResetPasswordPage = lazy(() => import('./features/auth/AuthPages').then((module) => ({ default: module.ResetPasswordPage })))
const VerifyEmailPage = lazy(() => import('./features/auth/AuthPages').then((module) => ({ default: module.VerifyEmailPage })))
const VerifyResultPage = lazy(() => import('./features/auth/AuthPages').then((module) => ({ default: module.VerifyResultPage })))
const ProfilePage = lazy(() => import('./features/profile/ProfilePage').then((module) => ({ default: module.ProfilePage })))
const SettingsPage = lazy(() => import('./features/settings/SettingsPage').then((module) => ({ default: module.SettingsPage })))

function LoadingScreen() { return <div className="route-loading" role="status">Loading workspace…</div> }
function Lazy({ children }: { children: ReactNode }) { return <Suspense fallback={<LoadingScreen />}>{children}</Suspense> }

function ProtectedLayout({ roles }: { roles?: string[] }) {
  const query = useQuery({ queryKey: ['me'], queryFn: authService.me, retry: false })
  if (query.isLoading) return <LoadingScreen />
  if (query.error || !query.data) return <Navigate to="/login" replace />
  if (roles && !roles.includes(query.data.user.role)) return <PlaceholderPage title="Access restricted" description="Your account does not have permission to open this workspace." icon={ShieldCheck} />
  return <Outlet context={query.data.user} />
}

function MemberShell() { const user = useOutletContext<import('./types').User>(); return <MemberLayout user={user} /> }
function AdminShell() { const user = useOutletContext<import('./types').User>(); return <AdminLayout user={user} /> }

export default function App() {
  return <><ThemeSync /><Routes>
    <Route element={<PublicLayout />}>
      <Route path="/" element={<HomePage />} />
      <Route path="/about" element={<PublicInfoPage title="About Social Discovery" description="A platform for finding community through shared interests and moving at a human pace." />} />
      <Route path="/features" element={<PublicInfoPage title="Features" description="Explore the foundation we are building for thoughtful social discovery." />} />
      <Route path="/explore" element={<PublicInfoPage title="Discover" description="Discovery will open up as people, interests, and communities become available." />} />
      <Route path="/community-guide" element={<PublicInfoPage title="Communities" description="Community spaces will be introduced with clear guidelines and thoughtful moderation." />} />
      <Route path="/event-guide" element={<PublicInfoPage title="Events" description="Events will help people gather around shared interests and experiences." />} />
      <Route path="/contact" element={<PublicInfoPage title="Contact" description="A simple place to find support and share feedback." />} />
      <Route path="/privacy" element={<PublicInfoPage title="Privacy Policy" description="Clear choices and respectful handling of personal data." />} />
      <Route path="/terms" element={<PublicInfoPage title="Terms of Service" description="The shared expectations for participating here." />} />
      <Route path="/guidelines" element={<PublicInfoPage title="Community Guidelines" description="Help make Social Discovery welcoming and safe." />} />
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
        <Route path="/discover" element={<PlaceholderPage title="Discover" description="Find people and communities through the interests you share." icon={Compass} />} />
        <Route path="/people" element={<PlaceholderPage title="People" description="A thoughtful directory for meeting people with shared interests." icon={Users} />} />
        <Route path="/connections" element={<PlaceholderPage title="Connections" description="Your growing circle, kept at your pace." icon={HeartHandshake} />} />
        <Route path="/feed" element={<PlaceholderPage title="Feed" description="A future home for updates from your communities." icon={Sparkles} />} />
        <Route path="/messages" element={<PlaceholderPage title="Messages" description="Private conversations are part of the next layer." icon={MessageCircle} />} />
        <Route path="/communities" element={<PlaceholderPage title="Communities" description="Find spaces organized around the things you care about." icon={Users} />} />
        <Route path="/events" element={<PlaceholderPage title="Events" description="Join gatherings, workshops, and shared experiences." icon={CalendarDays} />} />
        <Route path="/notifications" element={<PlaceholderPage title="Notifications" description="Your relevant updates will appear here as the platform grows." icon={Bell} />} />
        <Route path="/profile" element={<Lazy><ProfilePage /></Lazy>} />
        <Route path="/settings" element={<Lazy><SettingsPage /></Lazy>} />
      </Route>
    </Route>
    <Route element={<ProtectedLayout roles={['super_admin']} />}>
      <Route path="/admin" element={<AdminShell />}>
        <Route index element={<Navigate to="overview" replace />} />
        <Route path="overview" element={<PlaceholderPage title="Admin overview" description="A protected workspace for platform health and stewardship." icon={ShieldCheck} />} />
        <Route path="users" element={<PlaceholderPage title="User management" description="Review account status with documented administrative access." icon={Users} />} />
        <Route path="moderation" element={<PlaceholderPage title="Content moderation" description="A protected workspace for community care and safety." icon={ShieldCheck} />} />
        <Route path="reports" element={<PlaceholderPage title="Reports" description="Review reports and record accountable outcomes." icon={Bell} />} />
        <Route path="settings" element={<PlaceholderPage title="Platform settings" description="Manage system defaults and operational controls." icon={Settings} />} />
      </Route>
    </Route>
    <Route element={<ProtectedLayout roles={['moderator', 'super_admin']} />}><Route path="/moderator/overview" element={<PlaceholderPage title="Moderation workspace" description="A restricted workspace for reviewing community reports." icon={ShieldCheck} />} /></Route>
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes></>
}
