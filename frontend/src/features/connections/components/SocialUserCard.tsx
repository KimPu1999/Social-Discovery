import { Link } from 'react-router-dom'
import { useTranslation } from '../../../layouts/AppLayouts'
import { Avatar } from '../../../components/ui'
import type { RelationshipState, SocialUser } from '../types'

export type SocialUserCardProps = {
  user: SocialUser
  /** Context caption drawn under the username (connected at / blocked at). */
  caption?: string | null
  /** Viewer state for this row; null rows (own account) render no actions. */
  relationship?: RelationshipState | null
  /** Optional action area (follow/connect/respond buttons). */
  actions?: React.ReactNode
  compact?: boolean
}

/**
 * One person row used by every connection list: avatar, display name,
 * handle and short bio, plus a link into the public profile preview.
 */
export function SocialUserCard({ user, caption, relationship, actions, compact = false }: SocialUserCardProps) {
  const { t } = useTranslation()
  const path = user.username ? `/people/${user.username}` : null

  return (
    <article className={`social-row ${compact ? 'social-row-compact' : ''}`}>
      <Avatar name={user.display_name} src={user.avatar_url ?? undefined} />
      <div className="social-row-id">
        <h3 className="social-row-name">
          {path ? <Link to={path}>{user.display_name}</Link> : user.display_name}
        </h3>
        <p className="social-row-username">{user.username ? `@${user.username}` : ''}{caption ? ` · ${caption}` : ''}</p>
        {user.bio ? <p className="social-row-bio">{user.bio}</p> : null}
        {relationship ? (
          <p className="social-row-state" aria-live="polite">
            {relationship.is_following ? t.connections.rows.following : relationship.is_followed_by ? t.connections.rows.followsYou : relationship.is_connected ? t.connections.rows.connected : t.connections.rows.notFollowing}
          </p>
        ) : null}
      </div>
      {actions ? <div className="social-row-actions">{actions}</div> : null}
    </article>
  )
}
