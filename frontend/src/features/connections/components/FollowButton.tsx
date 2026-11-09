import { useState } from 'react'
import { Check, UserPlus } from 'lucide-react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Button, ConfirmDialog, useToast } from '../../../components/ui'
import { useFollowActions } from '../hooks/useConnections'
import type { RelationshipState } from '../types'

type FollowButtonProps = {
  userId: number
  relationship: RelationshipState
  size?: 'sm' | 'md'
}

/**
 * Follow / unfollow control driven entirely by server relationship state.
 * Unfollow asks for confirmation; success toasts appear only after the
 * API confirms, and failed actions stay silent (state never lies).
 */
export function FollowButton({ userId, relationship, size = 'sm' }: FollowButtonProps) {
  const { t } = useTranslation()
  const toast = useToast()
  const actions = useFollowActions()
  const [confirming, setConfirming] = useState(false)

  const busy = actions.follow.isPending || actions.unfollow.isPending
  const following = relationship.is_following
  const allowed = following || relationship.can_follow

  if (!allowed) return null

  const onToggle = () => {
    if (following) {
      setConfirming(true)
      return
    }
    actions.follow.mutate(userId, {
      onSuccess: () => toast('success', t.connections.follow.done),
      onError: (error) => toast('danger', error instanceof Error ? error.message : t.connections.errors.actionFailed),
    })
  }

  const onUnfollow = () => {
    actions.unfollow.mutate(userId, {
      onSuccess: () => {
        setConfirming(false)
        toast('info', t.connections.follow.removed)
      },
      onError: (error) => {
        setConfirming(false)
        toast('danger', error instanceof Error ? error.message : t.connections.errors.actionFailed)
      },
    })
  }

  return (
    <>
      <Button variant={following ? 'soft' : 'primary'} size={size} loading={busy} disabled={busy} onClick={onToggle}>
        {following && <Check size={14} aria-hidden="true" />}
        {!following && <UserPlus size={14} aria-hidden="true" />}
        {following ? t.connections.follow.following : t.connections.follow.follow}
      </Button>
      <ConfirmDialog
        open={confirming}
        title={t.connections.follow.unfollowTitle}
        description={t.connections.follow.unfollowCopy}
        confirmLabel={t.connections.follow.unfollowConfirm}
        cancelLabel={t.actions.cancel}
        loading={actions.unfollow.isPending}
        onConfirm={onUnfollow}
        onClose={() => setConfirming(false)}
      />
    </>
  )
}
