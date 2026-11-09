import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'
import { Compass, SearchX, Sparkles, UserX } from 'lucide-react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { EmptyState } from '../../../components/ui'

export type DiscoveryEmptyVariant = 'noResults' | 'noEligible' | 'noMatchingInterests' | 'recommendedNone' | 'disabled'

const icons: Record<DiscoveryEmptyVariant, { Icon: LucideIcon; titleKey: string; descKey: string }> = {
  noResults: { Icon: SearchX, titleKey: 'noResults', descKey: 'noResultsCopy' },
  noEligible: { Icon: UserX, titleKey: 'noEligible', descKey: 'noEligibleCopy' },
  noMatchingInterests: { Icon: SearchX, titleKey: 'noMatchingInterests', descKey: 'noMatchingInterestsCopy' },
  recommendedNone: { Icon: Sparkles, titleKey: 'recommendedNone', descKey: 'recommendedNoneCopy' },
  disabled: { Icon: Compass, titleKey: 'disabled', descKey: 'disabledCopy' },
}

/** Empty-state wrapper translating the discovery copy per variant. */
export function DiscoveryEmptyState({ variant, action }: { variant: DiscoveryEmptyVariant; action?: ReactNode }) {
  const { t } = useTranslation()
  const { Icon, titleKey, descKey } = icons[variant]
  const copy = t.discovery.empty
  return <EmptyState icon={<Icon size={26} aria-hidden="true" />} title={copy[titleKey as keyof typeof copy]} description={copy[descKey as keyof typeof copy]} action={action} />
}
