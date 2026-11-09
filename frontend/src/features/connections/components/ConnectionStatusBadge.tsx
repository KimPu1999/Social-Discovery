import { useTranslation } from '../../../layouts/AppLayouts'
import { Badge } from '../../../components/ui'
import type { RelationshipState } from '../types'

/**
 * Compact relationship indicator for cards and list rows. Only states the
 * API actually sent are shown — the blocked direction is never exposed.
 */
export function ConnectionStatusBadge({ relationship }: { relationship: RelationshipState }) {
  const { t } = useTranslation()

  if (relationship.is_connected) return <Badge tone="success">{t.connections.badge.connected}</Badge>
  if (relationship.request_status === 'incoming_pending') return <Badge tone="warning">{t.connections.badge.incomingPending}</Badge>
  if (relationship.request_status === 'outgoing_pending') return <Badge>{t.connections.badge.outgoingPending}</Badge>
  if (relationship.is_followed_by || relationship.is_following) return <Badge tone="primary">{t.connections.badge.following}</Badge>

  return null
}
