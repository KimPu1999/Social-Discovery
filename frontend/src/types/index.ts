export type Theme = 'light' | 'dark' | 'system'

export type User = {
  id: number
  name: string
  username: string
  email?: string
  status: string
  role: string
  email_verified?: boolean
}

export type Profile = { id: number; display_name: string; username: string; bio?: string; location?: string; website_url?: string; avatar_url?: string; cover_image_url?: string; onboarding_completed_at?: string; interests: Interest[] }
export type Interest = { id: number; name: string; slug: string; category: string }
export type Settings = { language: 'en' | 'my'; theme: 'light' | 'dark' | 'system'; email_notifications: boolean; push_notifications: boolean }
export type PrivacySettings = { profile_visibility: 'public' | 'connections' | 'private'; discovery_enabled: boolean; show_online_status: boolean; allow_message_requests: boolean; allow_connection_requests: boolean; allow_follows: boolean; show_interests: boolean; show_location: boolean; show_followers: boolean; show_following: boolean; show_connections: boolean; show_mutual_connections: boolean }

/** Laravel paginator envelope returned by admin endpoints. */
export type Paginated<T> = { data: T[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }
export type AdminUser = { id: number; name: string; username: string; email?: string; status: string; role: string; created_at?: string }
export type StatusValue = 'active' | 'suspended' | 'deactivated' | 'pending_verification'

