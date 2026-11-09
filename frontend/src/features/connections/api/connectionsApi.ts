import { api, csrf } from '../../../services/api'
import type {
  BlockedRow,
  ConnectionRow,
  FollowEdgeRow,
  IncomingRequest,
  MutualConnections,
  NotificationPage,
  OutgoingRequest,
  RelationshipResponse,
  RelationshipState,
  SocialListParams,
  SocialPageMeta,
  SocialNotification,
} from '../types'
import type { DiscoveryProfile } from '../../discovery/types'

type ListParams = { params?: SocialListParams }

/**
 * Social connections HTTP surface (Phase 05). Read calls run through the
 * shared axios client; every mutating call refreshes the CSRF cookie
 * first, matching the platform's session-based Sanctum conventions.
 * Success is only trusted after the server responds — no optimistic data.
 */
export const connectionsApi = {
  // ----- Follows -----
  async follow(userId: number) {
    await csrf()
    const { data } = await api.post<{ message: string; followed: boolean; relationship: RelationshipState }>(`/users/${userId}/follow`)
    return data
  },

  async unfollow(userId: number) {
    await csrf()
    const { data } = await api.delete<{ message: string; followed: boolean; relationship: RelationshipState }>(`/users/${userId}/follow`)
    return data
  },

  async relationship(userId: number) {
    const { data } = await api.get<RelationshipResponse>(`/users/${userId}/relationship`)
    return data
  },

  async followers(userId: number, { params = {} }: ListParams = {}) {
    const { data } = await api.get<{ data: FollowEdgeRow[]; meta: SocialPageMeta; relationship: RelationshipState | null }>(`/users/${userId}/followers`, { params })
    return data
  },

  async following(userId: number, { params = {} }: ListParams = {}) {
    const { data } = await api.get<{ data: FollowEdgeRow[]; meta: SocialPageMeta; relationship: RelationshipState | null }>(`/users/${userId}/following`, { params })
    return data
  },

  async mutualConnections(userId: number) {
    const { data } = await api.get<MutualConnections>(`/users/${userId}/mutual-connections`)
    return data
  },

  // ----- Friend requests -----
  async sendRequest(receiverId: number) {
    await csrf()
    const { data } = await api.post<{ message: string; created: boolean; relationship: RelationshipState }>('/connections/requests', { receiver_id: receiverId })
    return data
  },

  async incomingRequests({ params = {} }: ListParams = {}) {
    const { data } = await api.get<{ data: IncomingRequest[]; meta: SocialPageMeta }>('/connections/requests/incoming', { params })
    return data
  },

  async outgoingRequests({ params = {} }: ListParams = {}) {
    const { data } = await api.get<{ data: OutgoingRequest[]; meta: SocialPageMeta }>('/connections/requests/outgoing', { params })
    return data
  },

  async acceptRequest(requestId: number) {
    await csrf()
    const { data } = await api.post<{ message: string; relationship: RelationshipState }>(`/connections/requests/${requestId}/accept`)
    return data
  },

  async declineRequest(requestId: number) {
    await csrf()
    const { data } = await api.post<{ message: string }>(`/connections/requests/${requestId}/decline`)
    return data
  },

  async cancelRequest(requestId: number) {
    await csrf()
    const { data } = await api.post<{ message: string }>(`/connections/requests/${requestId}/cancel`)
    return data
  },

  // ----- Connections -----
  async myConnections({ params = {} }: ListParams = {}) {
    const { data } = await api.get<{ data: ConnectionRow[]; meta: SocialPageMeta }>('/connections', { params })
    return data
  },

  async suggestions({ params = {} }: ListParams = {}) {
    const { data } = await api.get<{ data: DiscoveryProfile[]; meta: SocialPageMeta }>('/connections/suggestions', { params })
    return data
  },

  async userConnections(userId: number, { params = {} }: ListParams = {}) {
    const { data } = await api.get<{ data: ConnectionRow[]; meta: SocialPageMeta; relationship: RelationshipState | null }>(`/users/${userId}/connections`, { params })
    return data
  },

  async removeConnection(userId: number) {
    await csrf()
    const { data } = await api.delete<{ message: string; relationship: RelationshipState }>(`/connections/${userId}`)
    return data
  },

  // ----- Blocking -----
  async block(userId: number) {
    await csrf()
    const { data } = await api.post<{ message: string; blocked: boolean }>(`/users/${userId}/block`)
    return data
  },

  async unblock(userId: number) {
    await csrf()
    const { data } = await api.delete<{ message: string; blocked: boolean }>(`/users/${userId}/block`)
    return data
  },

  async blockedUsers({ params = {} }: ListParams = {}) {
    const { data } = await api.get<{ data: BlockedRow[]; meta: SocialPageMeta }>('/settings/blocked-users', { params })
    return data
  },

  // ----- Notifications -----
  async notifications({ params = {} }: ListParams = {}) {
    const { data } = await api.get<NotificationPageResponse>('/notifications', { params })
    return data
  },

  async unreadCount() {
    const { data } = await api.get<{ unread_count: number }>('/notifications/unread-count')
    return data.unread_count
  },

  async markRead(notificationId: string) {
    await csrf()
    const { data } = await api.post<{ message: string; unread_count: number }>(`/notifications/${notificationId}/read`)
    return data
  },

  async markAllRead() {
    await csrf()
    const { data } = await api.post<{ message: string; unread_count: number }>('/notifications/read-all')
    return data
  },
}

type NotificationPageResponse = NotificationPage & { data: SocialNotification[] }
