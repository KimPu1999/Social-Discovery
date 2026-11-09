import { api } from './api'
import type { DiscoveryProfile } from '../features/discovery/types'

export type DashboardOverview = {
  profile: { display_name: string; username: string; bio?: string | null; avatar_url?: string | null; interests: { id: number; name: string; category: string }[] }
  profile_completion: { percentage: number; missing: string[] }
  connection_summary: { connections: number; followers: number; following: number; pending_requests: number }
  suggested_people: DiscoveryProfile[]
  recent_activity: { id: string; type: string; actor?: { display_name?: string; username?: string; id?: number } | null; url: string; read_at: string | null; created_at: string | null }[]
  feed_preview: { id: number; body?: string | null; published_at?: string | null; author: { id: number; username: string | null; display_name: string; avatar_url?: string | null }; media: { url?: string | null }[]; counts: { reactions: number; comments: number } }[]
  interests: { id: number; name: string; category: string; people_count: number }[]
  discovery_status: { enabled: boolean; visibility: string; allow_connection_requests: boolean }
  community_preview: { available: boolean; data: unknown[] }
  event_preview: { available: boolean; data: unknown[] }
}

export const dashboardService = {
  async overview(signal?: AbortSignal) {
    const { data } = await api.get<{ data: DashboardOverview }>('/dashboard/overview', { signal })
    return data.data
  },
}
