import { api } from '../../../services/api'

export type DiscoveryPost = {
  id: number
  body?: string | null
  published_at?: string | null
  created_at?: string | null
  author: { id: number; username?: string | null; display_name: string; avatar_url?: string | null }
  hashtags: { name: string; slug: string }[]
  counts: { reactions: number; comments: number; shares: number }
  viewer: { liked: boolean; saved: boolean }
}

export type FeedPage = { data: DiscoveryPost[]; meta: { type: string; next_cursor: string | null; has_more: boolean } }
export type TrendingTag = { id: number; name: string; slug: string; posts_count: number }
export type TrendingPage = { data: TrendingTag[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }
export type SavedPostRow = { saved_at: string; post: DiscoveryPost }
export type SavedPostPage = { data: SavedPostRow[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }

/** Read-only discovery surfaces backed by the existing privacy-aware feed APIs. */
export const discoveryContentApi = {
  async feed(type: 'for_you' | 'latest' = 'for_you', limit = 6) {
    const { data } = await api.get<FeedPage>('/feed', { params: { type, limit } })
    return data
  },

  async trending() {
    const { data } = await api.get<TrendingPage>('/hashtags', { params: { per_page: 12 } })
    return data
  },

  async saved() {
    const { data } = await api.get<SavedPostPage>('/me/saved-posts', { params: { per_page: 6 } })
    return data
  },
}
