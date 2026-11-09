import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Camera, Check, ImagePlus, Link2, MapPin, Pencil, Save, Sparkles, UserRound, X } from 'lucide-react'
import { profileService } from '../../services/profileService'
import { interestService } from '../../services/interestService'
import { useTranslation } from '../../layouts/AppLayouts'

type ProfileValues = { display_name: string; username: string; bio: string; location: string; website_url: string }

export function ProfilePage() {
  const { t } = useTranslation()
  const client = useQueryClient()
  const [editing, setEditing] = useState(false)
  const profile = useQuery({ queryKey: ['profile'], queryFn: profileService.get })
  const interests = useQuery({ queryKey: ['interests'], queryFn: interestService.list })
  const mine = useQuery({ queryKey: ['profile-interests'], queryFn: profileService.interests, enabled: Boolean(profile.data) })
  const form = useForm<ProfileValues>({ values: profile.data ? { display_name: profile.data.display_name ?? '', username: profile.data.username ?? '', bio: profile.data.bio ?? '', location: profile.data.location ?? '', website_url: profile.data.website_url ?? '' } : undefined })
  const update = useMutation({ mutationFn: profileService.update, onSuccess: () => { client.invalidateQueries({ queryKey: ['profile'] }); setEditing(false) } })
  const upload = useMutation({ mutationFn: ({ field, file }: { field: 'avatar' | 'cover'; file: File }) => profileService.upload(field, file), onSuccess: () => client.invalidateQueries({ queryKey: ['profile'] }) })
  const saveInterests = useMutation({ mutationFn: profileService.updateInterests, onSuccess: () => { client.invalidateQueries({ queryKey: ['profile-interests'] }); client.invalidateQueries({ queryKey: ['dashboard-overview'] }) } })

  if (profile.isLoading) return <div className="loading-state" role="status">{t.profile.loading}</div>
  if (profile.error || !profile.data) return <div className="empty-state"><h2>{t.profile.unavailable}</h2><p>{t.profile.unavailableCopy}</p></div>

  const current = profile.data
  const selected = new Set((mine.data ?? []).map((item) => item.id))
  const toggleInterest = (id: number) => { const next = new Set(selected); if (next.has(id)) next.delete(id); else next.add(id); saveInterests.mutate([...next]) }
  const uploadFile = (field: 'avatar' | 'cover', files: FileList | null) => { const file = files?.[0]; if (file) upload.mutate({ field, file }) }

  return <section className="page profile-page">
    <div className="profile-hero">
      <div className="profile-cover" style={current.cover_image_url ? { backgroundImage: `url(${current.cover_image_url})` } : undefined}>
        <div className="profile-cover-glow" />
        <label className="profile-cover-upload"><ImagePlus size={15} /><span>{current.cover_image_url ? 'Change cover' : 'Add cover'}</span><input type="file" accept="image/jpeg,image/png,image/webp" hidden onChange={(event) => uploadFile('cover', event.target.files)} /></label>
      </div>
      <div className="profile-identity">
        <label className="profile-avatar profile-avatar-edit">{current.avatar_url ? <img src={current.avatar_url} alt={`${current.display_name} avatar`} /> : <UserRound size={34} />}<span><Camera size={14} /></span><input type="file" accept="image/jpeg,image/png,image/webp" hidden onChange={(event) => uploadFile('avatar', event.target.files)} /></label>
        <div className="profile-identity-copy"><p className="eyebrow">Your profile</p><h1>{current.display_name}</h1><p className="profile-handle">@{current.username}</p><div className="profile-quick-meta">{current.location && <span><MapPin size={13} />{current.location}</span>}{current.website_url && <a href={current.website_url} target="_blank" rel="noreferrer"><Link2 size={13} />Website</a>}</div></div>
        <div className="profile-hero-actions"><button className="ui-button ui-button-soft ui-button-sm" type="button" onClick={() => setEditing((value) => !value)}>{editing ? <><X size={15} />{t.actions.cancel}</> : <><Pencil size={15} />{t.dashboard.editProfile}</>}</button></div>
      </div>
      <div className="profile-stat-strip"><div><strong>{selected.size}</strong><span>Interests</span></div><div><strong>{current.bio ? 'Ready' : 'Add'}</strong><span>Introduction</span></div><div><strong>{current.avatar_url ? 'Added' : 'Add'}</strong><span>Profile photo</span></div></div>
    </div>

    <div className="profile-content-grid">
      <section className="profile-section-card profile-about-card"><div className="profile-section-heading"><div><p className="eyebrow">{t.profile.about}</p><h2>{t.profile.details}</h2></div><span className="profile-section-icon"><UserRound size={17} /></span></div>
        {editing ? <form className="profile-form profile-edit-form" onSubmit={form.handleSubmit((values) => update.mutate(values))}><label>{t.auth.fullName}<input {...form.register('display_name')} aria-label={t.auth.fullName} /></label><label>{t.auth.username}<input {...form.register('username')} aria-label={t.auth.username} /></label><label>Bio<textarea {...form.register('bio')} rows={4} aria-label="Bio" placeholder="Tell people what brings you here" /></label><div className="profile-form-row"><label>Location<input {...form.register('location')} aria-label="Location" placeholder="Yangon, Myanmar" /></label><label>Website<input {...form.register('website_url')} aria-label="Website" placeholder="https://" /></label></div><div className="profile-form-actions"><button className="ui-button ui-button-primary ui-button-sm" type="submit" disabled={update.isPending}><Save size={15} />{update.isPending ? t.profile.saving : t.profile.saveProfile}</button><button className="ui-button ui-button-ghost ui-button-sm" type="button" onClick={() => setEditing(false)}>{t.actions.cancel}</button></div></form> : <div className="profile-about-content"><p className={current.bio ? '' : 'is-placeholder'}>{current.bio || t.profile.bioPlaceholder}</p><div className="profile-detail-list"><span><MapPin size={15} />{current.location || t.profile.locationHidden}</span><span><Link2 size={15} />{current.website_url || t.profile.noWebsite}</span></div></div>}
      </section>

      <section className="profile-section-card profile-interests-card"><div className="profile-section-heading"><div><p className="eyebrow">Explore interests</p><h2>{t.profile.interests}</h2></div><span className="profile-interest-count">{selected.size}/30</span></div><p className="profile-section-description">Choose what matters to you so we can find people with meaningful common ground.</p>{interests.isLoading ? <div className="profile-interest-loading">Loading interests…</div> : <div className="profile-interest-grid">{interests.data?.map((interest) => <button key={interest.id} type="button" className={selected.has(interest.id) ? 'profile-interest is-selected' : 'profile-interest'} onClick={() => toggleInterest(interest.id)} disabled={saveInterests.isPending}>{selected.has(interest.id) && <Check size={13} />}{interest.name}</button>)}</div>}<div className="profile-interest-footer"><Sparkles size={15} /><span>{selected.size ? 'Your interests shape your recommendations.' : 'Add a few interests to unlock recommendations.'}</span></div></section>
    </div>
  </section>
}
