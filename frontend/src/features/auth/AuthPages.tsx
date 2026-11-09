import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import { useForm, type UseFormRegister } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { Eye, EyeOff, LogIn, Sparkles } from 'lucide-react'
import { authService } from '../../services/authService'
import { useTranslation } from '../../layouts/AppLayouts'
import { interpolate, type Copy } from '../../i18n'

/** Schemas are rebuilt per locale so validation messages render in the active language. */
function buildSchemas(t: Copy) {

  const password = z.string().min(8, t.authErrors.min).regex(/[A-Z]/, t.authErrors.uppercase).regex(/[a-z]/, t.authErrors.lowercase).regex(/[0-9]/, t.authErrors.number)
  const login = z.object({ email: z.string().min(1, t.authErrors.required), password: z.string().min(1, t.authErrors.required), remember: z.boolean() })
  const register = z.object({ name: z.string().min(2, t.authErrors.name), username: z.string().min(3, t.authErrors.short).regex(/^[a-zA-Z0-9_-]+$/, t.authErrors.username), email: z.string().email(t.authErrors.email), password, password_confirmation: z.string(), terms_accepted: z.boolean().refine(Boolean, t.authErrors.terms), privacy_accepted: z.boolean().refine(Boolean, t.authErrors.terms) }).refine((value) => value.password === value.password_confirmation, { path: ['password_confirmation'], message: t.authErrors.match })
  const emailOnly = z.object({ email: z.string().email(t.authErrors.email) })
  const reset = z.object({ email: z.string().email(t.authErrors.email), password, password_confirmation: z.string() }).refine((value) => value.password === value.password_confirmation, { path: ['password_confirmation'], message: t.authErrors.match })
  return { login, register, emailOnly, reset }
}
type LoginValues = z.infer<ReturnType<typeof buildSchemas>['login']>
type RegisterValues = z.infer<ReturnType<typeof buildSchemas>['register']>

function FieldError({ message }: { message?: string }) { return message ? <small className="field-error" role="alert" aria-live="polite">{message}</small> : null }

function PasswordField({ register, name, error, t }: { register: UseFormRegister<Record<string, string | boolean>>; name: string; error?: string; t: Copy }) {
  const [visible, setVisible] = useState(false)
  return <div className="password-field"><input type={visible ? 'text' : 'password'} {...register(name)} placeholder={t.auth.passwordPlaceholder} autoComplete={name === 'password' ? 'current-password' : 'new-password'} aria-invalid={Boolean(error)} /><button type="button" onClick={() => setVisible((value) => !value)} aria-label={visible ? t.authErrors.hidePassword : t.authErrors.showPassword} aria-pressed={visible}>{visible ? <EyeOff size={16} /> : <Eye size={16} />}</button><FieldError message={error} /></div>
}

export function LoginPage() {
  const navigate = useNavigate()
  const { t } = useTranslation()
  const schemas = buildSchemas(t)
  const form = useForm<LoginValues>({ resolver: zodResolver(schemas.login), defaultValues: { remember: false } })
  const mutation = useMutation({ mutationFn: authService.login, onSuccess: (data) => navigate(data.user.role === 'super_admin' ? '/admin/overview' : data.user.role === 'moderator' ? '/moderator/overview' : '/dashboard') })
  return <div className="auth-card"><p className="eyebrow">{t.auth.welcomeBack}</p><h1>{t.auth.signInTitle}</h1><p className="muted">{t.auth.signInCopy}</p><form onSubmit={form.handleSubmit((values) => mutation.mutate(values))}><label>{t.auth.emailOrUsername}<input {...form.register('email')} placeholder={t.auth.emailPlaceholder} autoComplete="username" aria-invalid={Boolean(form.formState.errors.email)} /><FieldError message={form.formState.errors.email?.message} /></label><label>{t.auth.password}<PasswordField register={form.register as UseFormRegister<Record<string, string | boolean>>} name="password" error={form.formState.errors.password?.message} t={t} /></label><div className="form-row"><label className="check"><input type="checkbox" {...form.register('remember')} /> {t.auth.rememberMe}</label><Link to="/forgot-password">{t.auth.forgotPassword}</Link></div>{mutation.error && <div className="form-alert" role="alert">{(mutation.error as { message?: string }).message}</div>}<button className="button full" disabled={mutation.isPending} type="submit" aria-busy={mutation.isPending}>{mutation.isPending ? t.auth.signingIn : t.actions.signIn} <LogIn size={16} /></button></form><p className="auth-switch">{t.auth.noAccount} <Link to="/register">{t.auth.createAccount}</Link></p></div>
}

export function RegisterPage() {
  const navigate = useNavigate()
  const { t } = useTranslation()
  const schemas = buildSchemas(t)
  const form = useForm<RegisterValues>({ resolver: zodResolver(schemas.register), defaultValues: { terms_accepted: false, privacy_accepted: false } })
  const mutation = useMutation({ mutationFn: authService.register, onSuccess: () => navigate('/verify-email') })
  return <div className="auth-card"><p className="eyebrow">{t.auth.startEyebrow}</p><h1>{t.auth.registerTitle}</h1><p className="muted">{t.auth.registerCopy}</p><form onSubmit={form.handleSubmit((values) => mutation.mutate(values))}><label>{t.auth.fullName}<input {...form.register('name')} placeholder={t.auth.namePlaceholder} aria-invalid={Boolean(form.formState.errors.name)} /><FieldError message={form.formState.errors.name?.message} /></label><label>{t.auth.username}<input {...form.register('username')} placeholder={t.auth.usernamePlaceholder} aria-invalid={Boolean(form.formState.errors.username)} /><FieldError message={form.formState.errors.username?.message} /></label><label>{t.auth.email}<input type="email" {...form.register('email')} placeholder={t.auth.emailPlaceholder} autoComplete="email" aria-invalid={Boolean(form.formState.errors.email)} /><FieldError message={form.formState.errors.email?.message} /></label><label>{t.auth.password}<PasswordField register={form.register as UseFormRegister<Record<string, string | boolean>>} name="password" error={form.formState.errors.password?.message} t={t} /></label><label>{t.auth.confirmNewPassword}<input type="password" {...form.register('password_confirmation')} placeholder={t.auth.passwordPlaceholder} autoComplete="new-password" aria-invalid={Boolean(form.formState.errors.password_confirmation)} /><FieldError message={form.formState.errors.password_confirmation?.message} /></label><label className="check"><input type="checkbox" aria-invalid={Boolean(form.formState.errors.terms_accepted)} {...form.register('terms_accepted')} /> {interpolate(t.auth.acceptTerms, { doc: '' })}<Link to="/terms">{t.public.terms}</Link></label><FieldError message={form.formState.errors.terms_accepted?.message} /><label className="check"><input type="checkbox" aria-invalid={Boolean(form.formState.errors.privacy_accepted)} {...form.register('privacy_accepted')} /> {interpolate(t.auth.acceptPrivacy, { doc: '' })}<Link to="/privacy">{t.public.privacy}</Link></label><FieldError message={form.formState.errors.privacy_accepted?.message} />{mutation.error && <div className="form-alert" role="alert">{(mutation.error as { message?: string }).message}</div>}<button className="button full" disabled={mutation.isPending} type="submit" aria-busy={mutation.isPending}>{mutation.isPending ? t.auth.creatingAccount : t.actions.getStarted} <Sparkles size={16} /></button></form><p className="auth-switch">{t.auth.alreadyMember} <Link to="/login">{t.auth.signIn}</Link></p></div>
}

export function ForgotPasswordPage() {
  const { t } = useTranslation()
  const schemas = buildSchemas(t)
  const form = useForm<{ email: string }>({ resolver: zodResolver(schemas.emailOnly) })
  const mutation = useMutation({ mutationFn: (value: { email: string }) => authService.forgotPassword(value.email) })
  return <div className="auth-card"><p className="eyebrow">{t.auth.recoveryEyebrow}</p><h1>{t.auth.recoveryTitle}</h1><p className="muted">{t.auth.recoveryCopy}</p>{mutation.isSuccess ? <div className="success-state" role="status">{mutation.data.message}</div> : <form onSubmit={form.handleSubmit((values) => mutation.mutate(values))}><label>{t.auth.email}<input type="email" {...form.register('email')} placeholder={t.auth.emailPlaceholder} autoComplete="email" aria-invalid={Boolean(form.formState.errors.email)} /><FieldError message={form.formState.errors.email?.message} /></label><button className="button full" disabled={mutation.isPending} type="submit" aria-busy={mutation.isPending}>{mutation.isPending ? t.auth.sending : t.auth.sendInstructions}</button></form>}<p className="auth-switch"><Link to="/login">{t.auth.backToSignIn}</Link></p></div>
}

export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const { t } = useTranslation()
  const schemas = buildSchemas(t)
  const form = useForm<{ email: string; password: string; password_confirmation: string }>({ resolver: zodResolver(schemas.reset) })
  const mutation = useMutation({ mutationFn: (value: { email: string; password: string; password_confirmation: string }) => authService.resetPassword({ ...value, token: params.get('token') ?? '' }) })
  return <div className="auth-card"><p className="eyebrow">{t.auth.resetEyebrow}</p><h1>{t.auth.resetTitle}</h1><form onSubmit={form.handleSubmit((values) => mutation.mutate(values))}><label>{t.auth.email}<input type="email" {...form.register('email')} autoComplete="email" aria-invalid={Boolean(form.formState.errors.email)} /><FieldError message={form.formState.errors.email?.message} /></label><label>{t.auth.password}<PasswordField register={form.register as UseFormRegister<Record<string, string | boolean>>} name="password" error={form.formState.errors.password?.message} t={t} /></label><label>{t.auth.confirmNewPassword}<input type="password" {...form.register('password_confirmation')} autoComplete="new-password" aria-invalid={Boolean(form.formState.errors.password_confirmation)} /><FieldError message={form.formState.errors.password_confirmation?.message} /></label>{mutation.error && <div className="form-alert" role="alert">{(mutation.error as { message?: string }).message}</div>}{mutation.isSuccess && <div className="success-state" role="status">{t.auth.resetSuccess}</div>}<button className="button full" disabled={mutation.isPending} type="submit" aria-busy={mutation.isPending}>{mutation.isPending ? t.auth.sending : t.auth.resetTitle}</button></form><p className="auth-switch"><Link to="/login">{t.auth.backToSignIn}</Link></p></div>
}

export function VerifyEmailPage() {
  const { t } = useTranslation()
  const mutation = useMutation({ mutationFn: authService.resendVerification })
  return <div className="auth-card"><p className="eyebrow">{t.auth.verifyEyebrow}</p><h1>{t.auth.verifyTitle}</h1><p className="muted">{t.auth.verifyCopy}</p><button className="button full" onClick={() => mutation.mutate()} disabled={mutation.isPending} aria-busy={mutation.isPending}>{mutation.isPending ? t.auth.resending : t.auth.resend}</button>{mutation.data && <div className="success-state" role="status">{mutation.data.message}</div>}{mutation.error && <div className="form-alert" role="alert">{(mutation.error as { message?: string }).message}</div>}<p className="auth-switch"><Link to="/login">{t.auth.returnToSignIn}</Link></p></div>
}

export function VerifyResultPage() {
  const { t } = useTranslation()
  const [params] = useSearchParams()
  const success = params.get('status') === 'success'
  return <div className="auth-card"><p className="eyebrow">{t.auth.verifyTitle}</p><h1>{success ? t.auth.verifyOkTitle : t.auth.verifyFailTitle}</h1><p className="muted">{success ? t.auth.verifyOkCopy : t.auth.verifyFailCopy}</p><Link className="button full" to="/login">{t.auth.continueToSignIn}</Link></div>
}
