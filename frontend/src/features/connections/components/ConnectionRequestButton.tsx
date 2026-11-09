import { useTranslation } from '../../../layouts/AppLayouts'
import { Button, useToast } from '../../../components/ui'
import { useSendConnectionRequest } from '../hooks/useConnections'
import type { RelationshipState } from '../types'

type ConnectionRequestButtonProps = {
  userId: number
  relationship: RelationshipState
  size?: 'sm' | 'md'
}

/**
 * Connect control derived from server relationship state. Only rendered
 * when the API allows a new request — already-connected users, blocked
 * pairs and pending requests never render this button.
 */
export function ConnectionRequestButton({ userId, relationship, size = 'sm' }: ConnectionRequestButtonProps) {
  const { t } = useTranslation()
  const toast = useToast()
  const send = useSendConnectionRequest()

  if (!relationship.can_connect) return null

  return (
    <Button
      variant="secondary"
      size={size}
      loading={send.isPending}
      disabled={send.isPending}
      onClick={() => send.mutate(userId, {
        onSuccess: () => toast('success', t.connections.request.sentTitle, t.connections.request.sentCopy),
        onError: (error) => toast('danger', error instanceof Error ? error.message : t.connections.errors.actionFailed),
      })}
    >
      {t.connections.request.connect}
    </Button>
  )
}
