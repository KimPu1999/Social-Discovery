import { api, csrf } from '../../../services/api'
import type {
  DiscoveryInterestGroup,
  DiscoveryOverview,
  DiscoveryPreferences,
  DiscoveryProfile,
  DiscoveryProfileDetail,
  FeedbackType,
  PeopleFilters,
  PeoplePage,
} from '../types'

/**
 * Discovery HTTP surface. Every call goes through the shared axios client
 * (session-based Sanctum with CSRF); mutations refresh the CSRF cookie first,
 * matching the existing services' conventions.
 */
export const discoveryApi = {
  async overview() {
    const { data } = await api.get<{ overview: DiscoveryOverview }>('/discovery/overview')
    return data.overview
  },

  async people(filters: PeopleFilters) {
    const params: Record<string, unknown> = {
      sort: filters.sort,
      page: filters.page,
      per_page: filters.per_page,
    }
    if (filters.search.trim()) params.search = filters.search.trim()
    if (filters.interest_ids.length) params.interest_ids = filters.interest_ids
    const { data } = await api.get<PeoplePage>('/discovery/people', { params })
    return data
  },

  async recommended(limit = 8) {
    const { data } = await api.get<{ data: DiscoveryProfile[] }>('/discovery/recommended', { params: { limit } })
    return data.data
  },

  async interests() {
    const { data } = await api.get<{ data: DiscoveryInterestGroup[] }>('/discovery/interests')
    return data.data
  },

  async profile(username: string) {
    const { data } = await api.get<{ profile: DiscoveryProfileDetail }>(`/discovery/people/${encodeURIComponent(username)}`)
    return data.profile
  },

  async preferences() {
    const { data } = await api.get<{ preferences: DiscoveryPreferences }>('/discovery/preferences')
    return data.preferences
  },

  async updatePreferences(payload: Partial<DiscoveryPreferences>) {
    await csrf()
    const { data } = await api.put<{ preferences: DiscoveryPreferences }>('/discovery/preferences', payload)
    return data.preferences
  },

  async giveFeedback(targetUserId: number, feedbackType: FeedbackType) {
    await csrf()
    const { data } = await api.post<{ message: string }>('/discovery/feedback', {
      target_user_id: targetUserId,
      feedback_type: feedbackType,
    })
    return data.message
  },

  async undoFeedback(targetUserId: number) {
    const { data } = await api.delete<{ message: string; removed: number }>(`/discovery/feedback/${targetUserId}`)
    return data.message
  },
}
