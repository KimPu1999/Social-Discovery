import type { LucideIcon } from 'lucide-react'
import { Compass } from 'lucide-react'
import { Button, EmptyState } from '../components/ui'
import { useTranslation } from '../layouts/AppLayouts'

export function PlaceholderPage({ title, description, icon: Icon = Compass }: { title: string; description: string; icon?: LucideIcon }) {
  const { t } = useTranslation()
  return <section className="page-shell"><div className="page-heading"><div><p className="eyebrow">{t.nav.dashboard}</p><h1>{title}</h1><p className="page-description">{description}</p></div><span className="heading-icon"><Icon size={22} /></span></div><EmptyState icon={<Icon size={25} />} title={t.empty.title} description={t.empty.description} action={<Button variant="soft" disabled>{t.dashboard.comingSoon}</Button>} /></section>
}
