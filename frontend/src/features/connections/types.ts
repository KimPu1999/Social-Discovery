/**
 * Phase 05 shared types for connections, follows, friend requests,
 * blocking and notifications. Shapes mirror the Laravel resources 1:1 —
 * nothing sensitive (emails, statuses, block origin) is ever sent.
 */

/** Actor summary used in lists and notification payloads. */
export type SocialUser = {
  id: number
  username: string | null
  display_name: string
  bio?: string | null
  avatar_url?: string | null
}

/** Request state between the viewer and another profile. */
export type RequestStatus = 'none' | 'outgoing_pending' | 'incoming_pending'

/** Authorization-safe relationship state from the backend. */
export type RelationshipState = {
  is_following: boolean
  is_followed_by: boolean
  is_connected: boolean
  request_status: RequestStatus
  can_follow: boolean
  can_connect: boolean
  is_blocked_by_me: boolean
}

export const EMPTY_RELATIONSHIP: RelationshipState = {
  is_following: false,
  is_followed_by: false,
  is_connected: false,
  request_status: 'none',
  can_follow: false,
  can_connect: false,
  is_blocked_by_me: false,
}

/** Standard paginator meta shared by every list endpoint. */
export type SocialPageMeta = { current_page: number; last_page: number; per_page: number; total: number }

/** A row of a followers/following list. */
export type FollowEdgeRow = { user: SocialUser; followed_at: string | null; relationship: RelationshipState | null }

/** A row of a connections list. */
export type ConnectionRow = { user: SocialUser; connected_at: string | null; relationship: RelationshipState | null }

/** A row of the blocked-accounts list. */
export type BlockedRow = { user: SocialUser; blocked_at: string | null }

/** An incoming friend request addressed to the viewer. */
export type IncomingRequest = {
  id: number
  status: 'pending'
  created_at: string | null
  sender: SocialUser | null
  receiver: SocialUser | null
}

/** An outgoing friend request sent by the viewer. */
export type OutgoingRequest = {
  id: number
  status: 'pending'
  created_at: string | null
  sender: SocialUser | null
  receiver: SocialUser | null
}

/** Relationship + counts response for profile surfaces. */
export type RelationshipResponse = {
  relationship: RelationshipState | null
  counts: { followers: number | null; following: number | null; connections: number | null }
  mutual: { count: number; preview: SocialUser[] } | null
}

/** Mutual connections summary. */
export type MutualConnections = { count: number; data: SocialUser[] }

/** Notification feed row (database notifications channel). */
export type SocialNotification = {
  id: string
  type: 'new_follower' | 'connection_request' | 'connection_accepted' | (string & {})
  actor: { id: number; username: string; display_name: string } | null
  url: string
  read_at: string | null
  created_at: string | null
}

export type NotificationPage = { data: SocialNotification[]; meta: SocialPageMeta; unread_count: number }

/** Params accepted by paginated list endpoints. */
export type SocialListParams = { page?: number; per_page?: number; search?: string }

/** Tabs of the /connections workspace; preserved through the `tab` URL param. */
export const CONNECTION_TABS = ['connections', 'incoming', 'outgoing', 'followers', 'following', 'blocked'] as const
export type ConnectionTab = (typeof CONNECTION_TABS)[number]
