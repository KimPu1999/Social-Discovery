import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { Globe2, LockKeyhole, Palette, ShieldAlert, SlidersHorizontal, UserRound } from 'lucide-react'
import { settingsService } from '../../services/settingsService'
import { authService } from '../../services/authService'
import { useUiStore } from '../../stores/uiStore'
import { useTranslation } from '../../layouts/AppLayouts'
import { Alert, Button, ConfirmDialog, Select, Switch, useToast } from '../../components/ui'
import type { PrivacySettings } from '../../types'

const tabs = ['account', 'profile', 'privacy', 'security', 'notifications', 'appearance', 'language'] as const
type Tab = (typeof tabs)[number]
const password = z.string().min(8).regex(/[A-Z]/).regex(/[a-z]/).regex(/[0-9]/)

type PrivacyDraft = Pick<PrivacySettings, 'profile_visibility' | 'discovery_enabled' | 'allow_message_requests' | 'allow_connection_requests' | 'allow_follows' | 'show_interests' | 'show_location' | 'show_followers' | 'show_following' | 'show_connections' | 'show_mutual_connections' | 'show_online_status'>

const defaultPrivacy: PrivacyDraft = { profile_visibility: 'public', discovery_enabled: false, allow_message_requests: true, allow_connection_requests: true, allow_follows: true, show_interests: true, show_location: false, show_online_status: false, show_followers: true, show_following: true, show_connections: true, show_mutual_connections: true }

export function SettingsPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const client = useQueryClient()
  const toast = useToast()
  const { theme, setTheme, locale, setLocale } = useUiStore()
  const [tab, setTab] = useState<Tab>('account')
  const [privacyDraft, setPrivacyDraft] = useState<PrivacyDraft>(defaultPrivacy)
  const [dangerAction, setDangerAction] = useState<'deactivate' | 'delete' | null>(null)
  const [dangerPassword, setDangerPassword] = useState('')

  const me = useQuery({ queryKey: ['me'], queryFn: authService.me, staleTime: 60_000 })
  const settings = useQuery({ queryKey: ['settings'], queryFn: settingsService.get })
  const privacy = useQuery({ queryKey: ['privacy'], queryFn: settingsService.getPrivacy })
  const update = useMutation({ mutationFn: settingsService.update, onSuccess: () => { client.invalidateQueries({ queryKey: ['settings'] }); toast('success', t.settings.saved) } })
  const updatePrivacy = useMutation({ mutationFn: settingsService.updatePrivacy, onSuccess: () => { client.invalidateQueries({ queryKey: ['privacy'] }); toast('success', t.settings.saved) } })

  // Adopt authenticated preferences from the settings API (local store wins before authentication).
  useEffect(() => {
    const data = settings.data
    if (!data) return
    if (data.language && data.language !== locale) setLocale(data.language)
    if (data.theme && data.theme !== theme) setTheme(data.theme)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [settings.data])

  useEffect(() => { if (privacy.data) setPrivacyDraft({ ...defaultPrivacy, ...privacy.data }) }, [privacy.data])
  const privacyDirty = useMemo(() => Boolean(privacy.data) && JSON.stringify(privacyDraft) !== JSON.stringify(privacy.data), [privacyDraft, privacy.data])

  const savePrivacy = () => updatePrivacy.mutate(privacyDraft)
  const user = me.data?.user

  const passwordForm = useForm<{ current_password: string; password: string; password_confirmation: string }>({ resolver: zodResolver(z.object({ current_password: z.string().min(1), password, password_confirmation: z.string() }).refine((value) => value.password === value.password_confirmation, { path: ['password_confirmation'], message: 'Passwords do not match.' })) })
  const emailForm = useForm<{ email: string; password: string }>({ resolver: zodResolver(z.object({ email: z.string().email(), password: z.string().min(1) })) })
  const changePassword = useMutation({ mutationFn: (values: { current_password: string; password: string; password_confirmation: string }) => authService.updatePassword(values), onSuccess: () => { toast('success', t.settings.passwordUpdated); passwordForm.reset() }, onError: () => toast('danger', t.adminUsers.statusFailed) })
  const changeEmail = useMutation({ mutationFn: (values: { email: string; password: string }) => authService.updateEmail(values), onSuccess: (data) => { toast('success', t.settings.emailUpdated, data.message); emailForm.reset(); client.invalidateQueries({ queryKey: ['me'] }) }, onError: () => toast('danger', t.adminUsers.statusFailed) })
  const deactivate = useMutation({ mutationFn: () => authService.deactivate(dangerPassword), onSuccess: () => { toast('success', t.account.deactivated); client.clear(); navigate('/login') }, onError: () => toast('danger', t.adminUsers.statusFailed) })
  const submitPassword = passwordForm.handleSubmit((values) => changePassword.mutate(values))
  const submitEmail = emailForm.handleSubmit((values) => changeEmail.mutate(values))
  const requestDeletion = useMutation({ mutationFn: () => authService.deleteRequest(dangerPassword), onSuccess: () => { toast('success', t.account.deleteRequested); setDangerAction(null); setDangerPassword('') }, onError: () => toast('danger', t.adminUsers.statusFailed) })

  const tabMeta: Record<Tab, string> = { account: t.settings.account, profile: t.settings.profile, privacy: t.settings.privacy, security: t.settings.security, notifications: t.settings.notifications, appearance: t.settings.appearance, language: t.settings.language }
  const descriptionFor: Record<Tab, string> = { account: t.settings.accountCopy, profile: t.settings.editProfileCopy, privacy: t.settings.privacy, security: t.settings.securityCopy, notifications: t.settings.notificationsCopy, appearance: t.settings.appearanceCopy, language: t.settings.languageCopy }

  return <section className="page settings-page"><div className="page-heading"><div><p className="eyebrow">{t.brandLine}</p><h1>{t.settings.title}</h1><p className="muted">{t.settings.description}</p></div><SlidersHorizontal className="heading-icon" size={22} /></div>
    <div className="settings-layout">
      <nav className="settings-tabs" aria-label={t.settings.title}>{tabs.map((item) => <button key={item} className={tab === item ? 'active' : ''} aria-current={tab === item ? 'page' : undefined} onClick={() => setTab(item)}>{tabMeta[item]}</button>)}</nav>
      <div className="panel settings-panel">
        <h2 className="settings-panel-title">{tabMeta[tab]}</h2><p className="muted">{descriptionFor[tab]}</p>
        {(settings.isError || privacy.isError) && <Alert tone="danger" title={t.settings.loadFailed} />}
        {tab === 'account' && <>
          <dl className="settings-account-list"><div><dt>{t.adminUsers.tableUser}</dt><dd>{user ? `${user.name} (@${user.username})` : '—'}</dd></div><div><dt>{t.auth.email}</dt><dd>{user?.email ?? '—'} {user?.email_verified ? <Badgeish tone="ok">{t.account.emailVerified}</Badgeish> : <Badgeish tone="warn">{t.account.emailNotVerified}</Badgeish>}</dd></div><div><dt>{t.adminUsers.tableRole}</dt><dd>{user ? (t.roles[user.role as keyof typeof t.roles] ?? user.role) : '—'}</dd></div><div><dt>{t.settings.status}</dt><dd>{user ? (t.adminUsers[user.status as 'active' | 'deactivated'] ?? user.status) : '—'}</dd></div></dl>
          <p className="muted settings-danger-copy">{t.account.deactivationReminder}</p>
          <div className="choice-row"><Button variant="ghost" size="sm" onClick={() => { setDangerAction('deactivate'); setDangerPassword('') }}><ShieldAlert size={15} /> {t.account.deactivate}</Button><Button variant="ghost" size="sm" onClick={() => { setDangerAction('delete'); setDangerPassword('') }}>{t.account.requestDelete}</Button></div>
        </>}
        {tab === 'profile' && <div className="choice-row"><Link className="ui-button ui-button-soft" to="/profile"><UserRound size={16} /> {t.dashboard.editProfile}</Link></div>}
        {tab === 'privacy' && <>
          <Select label={t.settings.profileVisibility} value={privacyDraft.profile_visibility} options={[{ value: 'public', label: t.settings.profileVisibility }, { value: 'connections', label: t.nav.connections }, { value: 'private', label: t.settings.privacy }]} onChange={(event) => setPrivacyDraft({ ...privacyDraft, profile_visibility: event.target.value as PrivacyDraft['profile_visibility'] })} />
          <Switch label={t.settings.discoveryEnabled} checked={privacyDraft.discovery_enabled} onChange={(value) => setPrivacyDraft({ ...privacyDraft, discovery_enabled: value })} />
          <Switch label={t.settings.allowConnectionRequests} checked={privacyDraft.allow_connection_requests} onChange={(value) => setPrivacyDraft({ ...privacyDraft, allow_connection_requests: value })} />
          <Switch label={t.settings.allowMessageRequests} checked={privacyDraft.allow_message_requests} onChange={(value) => setPrivacyDraft({ ...privacyDraft, allow_message_requests: value })} />
          <Switch label={t.settings.allowFollows} checked={privacyDraft.allow_follows} onChange={(value) => setPrivacyDraft({ ...privacyDraft, allow_follows: value })} />
          <Switch label={t.settings.showFollowers} checked={privacyDraft.show_followers} onChange={(value) => setPrivacyDraft({ ...privacyDraft, show_followers: value })} />
          <Switch label={t.settings.showFollowing} checked={privacyDraft.show_following} onChange={(value) => setPrivacyDraft({ ...privacyDraft, show_following: value })} />
          <Switch label={t.settings.showConnections} checked={privacyDraft.show_connections} onChange={(value) => setPrivacyDraft({ ...privacyDraft, show_connections: value })} />
          <Switch label={t.settings.showMutualConnections} checked={privacyDraft.show_mutual_connections} onChange={(value) => setPrivacyDraft({ ...privacyDraft, show_mutual_connections: value })} />
          <Switch label={t.settings.showInterests} checked={privacyDraft.show_interests} onChange={(value) => setPrivacyDraft({ ...privacyDraft, show_interests: value })} />
          <Switch label={t.settings.showLocation} checked={privacyDraft.show_location} onChange={(value) => setPrivacyDraft({ ...privacyDraft, show_location: value })} />
          {privacyDirty && <Alert tone="warning" title={t.settings.unsaved}><div className="alert-actions"><Button variant="primary" size="sm" loading={updatePrivacy.isPending} onClick={savePrivacy}>{t.settings.save}</Button><Button variant="ghost" size="sm" onClick={() => privacy.data && setPrivacyDraft({ ...defaultPrivacy, ...privacy.data })}>{t.actions.cancel}</Button></div></Alert>}
          <Link className="text-button" to="/settings/discovery"><SlidersHorizontal size={14} /> {t.discovery.settings.title}</Link>
        </>}
        {tab === 'security' && <>
          <form className="stack-form" onSubmit={(event) => { event.preventDefault(); void submitPassword(event) }}>
            <label>{t.settings.currentPassword}<input type="password" autoComplete="current-password" {...passwordForm.register('current_password')} /></label>
            <label>{t.settings.newPassword}<input type="password" autoComplete="new-password" {...passwordForm.register('password')} /></label>
            <label>{t.settings.confirmPassword}<input type="password" autoComplete="new-password" {...passwordForm.register('password_confirmation')} /></label>
            {typeof passwordForm.formState.errors.password?.message === 'string' && passwordForm.formState.errors.password?.message && <small className="field-error">{passwordForm.formState.errors.password.message}</small>}
            <Button variant="primary" type="submit" loading={changePassword.isPending}><LockKeyhole size={15} /> {t.settings.updatePassword}</Button>
          </form>
          <form className="stack-form" onSubmit={(event) => { event.preventDefault(); void submitEmail(event) }}>            <label>{t.auth.email}<input type="email" autoComplete="email" {...emailForm.register('email')} /></label>
            <label>{t.settings.currentPassword}<input type="password" autoComplete="current-password" {...emailForm.register('password')} /></label>
            <Button variant="soft" type="submit" loading={changeEmail.isPending}>{t.settings.updateEmail}</Button>
          </form>
        </>}
        {tab === 'notifications' && <>
          <Switch label={t.settings.emailNotifications} checked={settings.data?.email_notifications ?? true} onChange={(value) => update.mutate({ email_notifications: value })} />
          <Switch label={t.settings.pushNotifications} checked={settings.data?.push_notifications ?? false} onChange={(value) => update.mutate({ push_notifications: value })} />
        </>}
        {tab === 'appearance' && <div className="choice-row">
          <Choice icon={<Palette size={17} />} label={t.settings.light} active={theme === 'light'} onClick={() => { setTheme('light'); update.mutate({ theme: 'light' }) }} />
          <Choice icon={<SlidersHorizontal size={17} />} label={t.settings.system} active={theme === 'system'} onClick={() => { setTheme('system'); update.mutate({ theme: 'system' }) }} />
          <Choice icon={<Palette size={17} />} label={t.settings.dark} active={theme === 'dark'} onClick={() => { setTheme('dark'); update.mutate({ theme: 'dark' }) }} />
        </div>}
        {tab === 'language' && <div className="choice-row">
          <Choice icon={<Globe2 size={17} />} label="English" active={locale === 'en'} onClick={() => { setLocale('en'); update.mutate({ language: 'en' }) }} />
          <Choice icon={<Globe2 size={17} />} label="မြန်မာ" active={locale === 'my'} onClick={() => { setLocale('my'); update.mutate({ language: 'my' }) }} />
        </div>}
      </div>
    </div>
    <ConfirmDialog open={Boolean(dangerAction)} danger loading={deactivate.isPending || requestDeletion.isPending}
      title={dangerAction === 'delete' ? t.account.requestDelete : t.account.deactivate} description={t.settings.accountConfirm}
      confirmLabel={t.actions.confirm} cancelLabel={t.actions.cancel} onClose={() => setDangerAction(null)}
      onConfirm={() => (dangerAction === 'delete' ? requestDeletion.mutate() : deactivate.mutate())}>
      <label className="ui-field"><span className="ui-label">{t.settings.currentPassword}</span><input type="password" value={dangerPassword} onChange={(event) => setDangerPassword(event.target.value)} autoFocus /></label>
    </ConfirmDialog>
  </section>
}

function Badgeish({ tone, children }: { tone: 'ok' | 'warn'; children: React.ReactNode }) { return <span className={`mini-badge ${tone === 'ok' ? 'mini-badge-ok' : 'mini-badge-warn'}`}>{children}</span> }
function Choice({ icon, label, active, onClick }: { icon: React.ReactNode; label: string; active: boolean; onClick: () => void }) { return <button type="button" className={active ? 'choice active' : 'choice'} aria-pressed={active} onClick={onClick}>{icon}<span>{label}</span></button> }
