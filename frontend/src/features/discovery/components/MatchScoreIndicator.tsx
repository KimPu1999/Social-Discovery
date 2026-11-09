import { useTranslation } from '../../../layouts/AppLayouts'
import { interpolate } from '../../../i18n'
import type { DiscoveryMatch } from '../types'

/**
 * Shows the match percentage only when the backend produced a meaningful
 * score (shared interests exist on both sides). Otherwise renders nothing —
 * no misleading "0% match".
 */
export function MatchScoreIndicator({ match }: { match: DiscoveryMatch | null }) {
  const { t } = useTranslation()
  if (!match) return null
  const tone = match.score >= 60 ? 'high' : match.score >= 30 ? 'medium' : 'low'
  const label = interpolate(t.discovery.match.sharedCount, { count: match.shared_count })
  return (
    <span className={`discovery-match discovery-match-${tone}`} title={label}>
      {Math.round(match.score)}%
      <span className="sr-only">{label}</span>
    </span>
  )
}

/** Human sentence explaining why this person is recommended. */
export function MatchExplanation({ match }: { match: DiscoveryMatch | null }) {
  const { t } = useTranslation()
  if (!match) return null
  const names = match.shared_interests.map((interest) => interest.name).join(', ')
  return <p className="discovery-match-explanation">{names ? interpolate(t.discovery.match.explanation, { names }) : t.discovery.match.noMatch}</p>
}
