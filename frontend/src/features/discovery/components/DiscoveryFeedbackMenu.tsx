import { EllipsisVertical, EyeOff, ShieldOff, ThumbsDown, Undo2 } from 'lucide-react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Dropdown } from '../../../components/ui'
import type { FeedbackType } from '../types'

type DiscoveryFeedbackMenuProps = {
  /** Give "not interested" / "hide profile" feedback. */
  onFeedback: (type: FeedbackType) => void
  /** Undo an existing hide — only offered when `hidden`. */
  onUndoFeedback?: () => void
  hidden?: boolean
  busy?: boolean
  /** Block is offered only where the caller has an authorized relationship. */
  onBlock?: () => void
}

/** Card/profile actions for discovery feedback: not interested, hide, unhide. */
export function DiscoveryFeedbackMenu({ onFeedback, onUndoFeedback, hidden = false, busy = false, onBlock }: DiscoveryFeedbackMenuProps) {
  const { t } = useTranslation()
  return (
    <Dropdown align="right" label={<><EllipsisVertical size={17} aria-hidden="true" /><span className="sr-only">{t.discovery.card.options}</span></>}>
      {hidden && onUndoFeedback ? (
        <button className="menu-item" role="menuitem" disabled={busy} onClick={onUndoFeedback}>
          <Undo2 size={15} aria-hidden="true" />
          {t.discovery.card.unhide}
        </button>
      ) : (
        <>
          <button className="menu-item" role="menuitem" disabled={busy} onClick={() => onFeedback('not_interested')}>
            <ThumbsDown size={15} aria-hidden="true" />
            {t.discovery.card.notInterested}
          </button>
          <button className="menu-item" role="menuitem" disabled={busy} onClick={() => onFeedback('hide_profile')}>
            <EyeOff size={15} aria-hidden="true" />
            {t.discovery.card.hideProfile}
          </button>
          {onBlock ? <button className="menu-item menu-item-danger" role="menuitem" disabled={busy} onClick={onBlock}>
            <ShieldOff size={15} aria-hidden="true" />
            {t.discovery.profile.blocking}
          </button> : null}
        </>
      )}
    </Dropdown>
  )
}
