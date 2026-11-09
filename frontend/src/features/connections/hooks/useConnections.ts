import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { discoveryKeys } from '../../discovery/hooks/useDiscovery'
import { connectionsApi } from '../api/connectionsApi'
import type { SocialListParams } from '../types'

/** Centralized query keys for every Phase 05 read. */
export const connectionsKeys = {
  all: ['connections'] as const,
  relationship: (userId: number) => [...connectionsKeys.all, 'relationship', userId] as const,
  followers: (userId: number, params: SocialListParams) => [...connectionsKeys.all, 'followers', userId, params] as const,
  following: (userId: number, params: SocialListParams) => [...connectionsKeys.all, 'following', userId, params] as const,
  mutual: (userId: number) => [...connectionsKeys.all, 'mutual', userId] as const,
  myConnections: (params: SocialListParams) => [...connectionsKeys.all, 'mine', params] as const,
  suggestions: (params: SocialListParams) => [...connectionsKeys.all, 'suggestions', params] as const,
  userConnections: (userId: number, params: SocialListParams) => [...connectionsKeys.all, 'user', userId, params] as const,
  incoming: (params: SocialListParams) => [...connectionsKeys.all, 'incoming', params] as const,
  outgoing: (params: SocialListParams) => [...connectionsKeys.all, 'outgoing', params] as const,
  blocked: (params: SocialListParams) => [...connectionsKeys.all, 'blocked', params] as const,
  notifications: ['notifications'] as const,
  notificationsPage: (params: SocialListParams) => [...connectionsKeys.notifications, 'page', params] as const,
}

/**
 * After any social mutation, refresh relationship-burning reads: the
 * connections space, the viewer's own relationship summaries and the
 * discovery results (relationship state is embedded in cards).
 */
function useSocialInvalidation() {
  const queryClient = useQueryClient()
  return () => {
    void queryClient.invalidateQueries({ queryKey: connectionsKeys.all })
    void queryClient.invalidateQueries({ queryKey: discoveryKeys.all })
  }
}

type DiscoveryCacheSnapshot = [readonly unknown[], unknown][]

/** Keep discovery cards responsive while the server confirms a follow change. */
function useOptimisticDiscoveryFollow() {
  const queryClient = useQueryClient()

  return {
    onMutate: async (userId: number, following: boolean): Promise<DiscoveryCacheSnapshot> => {
      await queryClient.cancelQueries({ queryKey: discoveryKeys.all })
      const snapshots = queryClient.getQueriesData({ queryKey: discoveryKeys.all })

      queryClient.setQueriesData({ queryKey: discoveryKeys.all }, (current: unknown) => updateDiscoveryRelationship(current, userId, following))
      return snapshots
    },
    onError: (_error: unknown, _userId: number, snapshots?: DiscoveryCacheSnapshot) => {
      snapshots?.forEach(([key, value]) => queryClient.setQueryData(key, value))
    },
  }
}

/** Apply an outgoing request state immediately to cards and profile previews. */
function updateDiscoveryConnection(current: unknown, userId: number): unknown {
  const updateProfile = (profile: unknown) => {
    if (!profile || typeof profile !== 'object' || (profile as { id?: number }).id !== userId) return profile
    const value = profile as { relationship?: Record<string, unknown> | null }
    if (!value.relationship) return profile
    return {
      ...value,
      relationship: {
        ...value.relationship,
        request_status: 'outgoing_pending',
        can_connect: false,
      },
    }
  }

  if (Array.isArray(current)) return current.map(updateProfile)
  if (!current || typeof current !== 'object') return current
  const value = current as { data?: unknown }
  if (Array.isArray(value.data)) return { ...value, data: value.data.map(updateProfile) }
  return updateProfile(current)
}

function restoreDiscoverySnapshots(queryClient: ReturnType<typeof useQueryClient>, snapshots?: DiscoveryCacheSnapshot) {
  snapshots?.forEach(([key, value]) => queryClient.setQueryData(key, value))
}

function updateDiscoveryRelationship(current: unknown, userId: number, following: boolean): unknown {
  const updateProfile = (profile: unknown) => {
    if (!profile || typeof profile !== 'object' || (profile as { id?: number }).id !== userId) return profile
    const value = profile as { relationship?: Record<string, unknown> | null }
    if (!value.relationship) return profile
    return {
      ...value,
      relationship: {
        ...value.relationship,
        is_following: following,
        can_follow: following ? false : true,
      },
    }
  }

  if (Array.isArray(current)) return current.map(updateProfile)
  if (!current || typeof current !== 'object') return current
  const value = current as { data?: unknown; id?: number; relationship?: Record<string, unknown> | null }
  if (Array.isArray(value.data)) return { ...value, data: value.data.map(updateProfile) }
  return updateProfile(value)
}

export function useRelationship(userId: number, enabled = true) {
  return useQuery({ queryKey: connectionsKeys.relationship(userId), queryFn: () => connectionsApi.relationship(userId), enabled, staleTime: 15_000 })
}

export function useMutualConnections(userId: number, enabled = true) {
  return useQuery({ queryKey: connectionsKeys.mutual(userId), queryFn: () => connectionsApi.mutualConnections(userId), enabled, staleTime: 30_000 })
}

export function useFollowers(userId: number, params: SocialListParams, enabled = true) {
  return useQuery({ queryKey: connectionsKeys.followers(userId, params), queryFn: () => connectionsApi.followers(userId, { params }), placeholderData: keepPreviousData, staleTime: 10_000, enabled })
}

export function useFollowing(userId: number, params: SocialListParams, enabled = true) {
  return useQuery({ queryKey: connectionsKeys.following(userId, params), queryFn: () => connectionsApi.following(userId, { params }), placeholderData: keepPreviousData, staleTime: 10_000, enabled })
}

export function useMyConnections(params: SocialListParams) {
  return useQuery({ queryKey: connectionsKeys.myConnections(params), queryFn: () => connectionsApi.myConnections({ params }), placeholderData: keepPreviousData, staleTime: 10_000 })
}

export function useConnectionSuggestions(params: SocialListParams) {
  return useQuery({ queryKey: connectionsKeys.suggestions(params), queryFn: () => connectionsApi.suggestions({ params }), placeholderData: keepPreviousData, staleTime: 15_000 })
}

export function useUserConnections(userId: number, params: SocialListParams) {
  return useQuery({ queryKey: connectionsKeys.userConnections(userId, params), queryFn: () => connectionsApi.userConnections(userId, { params }), placeholderData: keepPreviousData, staleTime: 10_000 })
}

export function useIncomingRequests(params: SocialListParams) {
  return useQuery({ queryKey: connectionsKeys.incoming(params), queryFn: () => connectionsApi.incomingRequests({ params }), placeholderData: keepPreviousData })
}

export function useOutgoingRequests(params: SocialListParams) {
  return useQuery({ queryKey: connectionsKeys.outgoing(params), queryFn: () => connectionsApi.outgoingRequests({ params }), placeholderData: keepPreviousData })
}

export function useBlockedUsers(params: SocialListParams) {
  return useQuery({ queryKey: connectionsKeys.blocked(params), queryFn: () => connectionsApi.blockedUsers({ params }), placeholderData: keepPreviousData })
}

export function useNotifications(params: SocialListParams) {
  return useQuery({ queryKey: connectionsKeys.notificationsPage(params), queryFn: () => connectionsApi.notifications({ params }), placeholderData: keepPreviousData })
}

export function useUnreadNotificationCount(enabled = true) {
  return useQuery({ queryKey: [...connectionsKeys.notifications, 'unread'], queryFn: connectionsApi.unreadCount, enabled, staleTime: 20_000 })
}

/** Follow and unfollow share one hook so callers keep a single pending flag. */
export function useFollowActions() {
  const invalidate = useSocialInvalidation()
  const optimistic = useOptimisticDiscoveryFollow()

  const follow = useMutation({
    mutationFn: connectionsApi.follow,
    onMutate: (userId) => optimistic.onMutate(userId, true),
    onError: optimistic.onError,
    onSuccess: invalidate,
  })
  const unfollow = useMutation({
    mutationFn: connectionsApi.unfollow,
    onMutate: (userId) => optimistic.onMutate(userId, false),
    onError: optimistic.onError,
    onSuccess: invalidate,
  })

  return { follow, unfollow }
}

export function useSendConnectionRequest() {
  const invalidate = useSocialInvalidation()
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: connectionsApi.sendRequest,
    onMutate: async (userId) => {
      await queryClient.cancelQueries({ queryKey: discoveryKeys.all })
      const snapshots = queryClient.getQueriesData({ queryKey: discoveryKeys.all })
      queryClient.setQueriesData({ queryKey: discoveryKeys.all }, (current: unknown) => updateDiscoveryConnection(current, userId))
      return snapshots
    },
    onError: (_error, _userId, snapshots) => restoreDiscoverySnapshots(queryClient, snapshots),
    onSuccess: invalidate,
  })
}

/** Accept / decline / cancel share one hook; each posts to its endpoint. */
export function useRequestResponse() {
  const invalidate = useSocialInvalidation()

  const accept = useMutation({ mutationFn: connectionsApi.acceptRequest, onSuccess: invalidate })
  const decline = useMutation({ mutationFn: connectionsApi.declineRequest, onSuccess: invalidate })
  const cancel = useMutation({ mutationFn: connectionsApi.cancelRequest, onSuccess: invalidate })

  return { accept, decline, cancel }
}

export function useRemoveConnection() {
  const invalidate = useSocialInvalidation()
  return useMutation({ mutationFn: connectionsApi.removeConnection, onSuccess: invalidate })
}

/** Block and unblock also tear down cached lists; keep them together. */
export function useBlockActions() {
  const invalidate = useSocialInvalidation()

  const block = useMutation({ mutationFn: connectionsApi.block, onSuccess: invalidate })
  const unblock = useMutation({ mutationFn: connectionsApi.unblock, onSuccess: invalidate })

  return { block, unblock }
}

export function useNotificationReads() {
  const queryClient = useQueryClient()

  const markRead = useMutation({
    mutationFn: connectionsApi.markRead,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: connectionsKeys.notifications })
    },
  })
  const markAllRead = useMutation({
    mutationFn: connectionsApi.markAllRead,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: connectionsKeys.notifications })
    },
  })

  return { markRead, markAllRead }
}
