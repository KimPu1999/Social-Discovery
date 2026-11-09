import { useMemo } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { LockKeyhole, SlidersHorizontal } from 'lucide-react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Alert, Badge, Card, CardHeader, InterestChip, Switch, useToast } from '../../../components/ui'
import { useDiscoveryInterests, useDiscoveryPreferences, useUpdateDiscoveryPreferences } from '../hooks/useDiscovery'
import { settingsService } from '../../../services/settingsService'

/** Route /settings/discovery — canonical discovery consent + Phase 02 privacy reuse. */
export function DiscoverySettingsPage() {
  const { t } = useTranslation()
  const toast = useToast()
  const preferences = useDiscoveryPreferences()
  const update = useUpdateDiscoveryPreferences()
  const interests = useDiscoveryInterests()

  // Phase 02 privacy settings are read-only here: their writes belong to the
  // privacy tab so the two screens can never contradict each other.
  const privacy = useQuery({ queryKey: ['privacy'], queryFn: settingsService.getPrivacy, staleTime: 60_000 })

  const categories = useMemo(
    () => Array.from(new Set((interests.data ?? []).map((group) => group.category))).sort(),
    [interests.data],
  )
  const selectedCategories = new Set(preferences.data?.preferred_interest_categories ?? [])

  const persist = (patch: Parameters<typeof update.mutate>[0]) => {
    update.mutate(patch, {
      onSuccess: () => toast('success', t.discovery.settings.saved),
      onError: () => toast('danger', t.discovery.settings.failed),
    })
  }

  const enabled = preferences.data?.discovery_enabled ?? false

  return (
    <div className="discovery-page">
      <header className="discovery-page-header">
        <div>
          <p className="eyebrow"><SlidersHorizontal size={14} aria-hidden="true" /> {t.nav.settings}</p>
          <h1>{t.discovery.settings.title}</h1>
          <p>{t.discovery.settings.description}</p>
        </div>
        <Badge tone={enabled ? 'success' : 'warning'}>{enabled ? t.discovery.badgeOn : t.discovery.badgeOff}</Badge>
      </header>

      {!enabled && <Alert tone="warning" title={t.discovery.settings.disabledTitle}>{t.discovery.settings.disabledCopy}</Alert>}

      <Card>
        <CardHeader title={t.discovery.settings.enableTitle} eyebrow={t.discovery.settings.title} />
        <div className="discovery-settings-controls">
          <Switch checked={enabled} label={t.discovery.settings.enableTitle} onChange={(checked) => persist({ discovery_enabled: checked })} />
          <p className="discovery-settings-hint">{t.discovery.settings.enableCopy}</p>
          <Switch
            checked={preferences.data?.show_in_recommendations ?? true}
            label={t.discovery.settings.recommendationsTitle}
            disabled={!enabled}
            onChange={(checked) => persist({ show_in_recommendations: checked })}
          />
          <p className="discovery-settings-hint">{t.discovery.settings.recommendationsCopy}</p>
        </div>
      </Card>

      <Card>
        <CardHeader title={t.discovery.settings.categoriesTitle} eyebrow={t.discovery.settings.categoriesCopy} />
        <div className="discovery-interest-row">
          {categories.map((category) => (
            <InterestChip
              key={category}
              selected={selectedCategories.has(category)}
              onClick={() => {
                const next = selectedCategories.has(category)
                  ? preferences.data?.preferred_interest_categories.filter((value) => value !== category)
                  : [...(preferences.data?.preferred_interest_categories ?? []), category]
                persist({ preferred_interest_categories: next })
              }}
            >
              {category}
            </InterestChip>
          ))}
        </div>
      </Card>

      <Card>
        <CardHeader title={t.discovery.settings.reuseTitle} eyebrow={t.discovery.settings.reuseCopy} />
        <dl className="discovery-privacy-summary">
          <div><dt>{t.discovery.settings.visibility}</dt><dd>{privacy.data?.profile_visibility ?? '—'}</dd></div>
          <div><dt>{t.discovery.settings.showInterests}</dt><dd>{privacy.data?.show_interests ? 'On' : 'Off'}</dd></div>
          <div><dt>{t.discovery.settings.allowConnection}</dt><dd>{privacy.data?.allow_connection_requests ? 'On' : 'Off'}</dd></div>
          <div><dt>{t.discovery.settings.allowMessage}</dt><dd>{privacy.data?.allow_message_requests ? 'On' : 'Off'}</dd></div>
        </dl>
        <Link className="text-button" to="/settings"><LockKeyhole size={14} aria-hidden="true" /> {t.discovery.settings.manageInPrivacy}</Link>
      </Card>
    </div>
  )
}
