/** Shared-interest reference (backend resources always map to this shape). */
export type DiscoveryInterestRef = { id: number; name: string; slug: string; category: string }

/** Interest shown on a discovery profile card; `shared_with_viewer` marks overlap with the viewer. */
export type DiscoveryInterest = DiscoveryInterestRef & { shared_with_viewer?: boolean }

/** Explainable match payload; `null` means "no meaningful score available yet". */
export type DiscoveryMatch = { score: number; shared_count: number; shared_interests: DiscoveryInterestRef[] }

/** Privacy-safe profile card payload from DiscoveryProfileResource. */
export type DiscoveryProfile = {
  id: number
  username: string
  display_name: string
  bio?: string | null
  location?: string | null
  avatar_url?: string | null
  joined_at?: string | null
  interests: DiscoveryInterest[]
  match: DiscoveryMatch | null
  /** Phase 05: orientation-safe relationship state, present only when computed by the API. */
  relationship?: import('../connections/types').RelationshipState | null
}

/** Public profile preview from DiscoveryProfileDetailResource. */
export type DiscoveryProfileDetail = DiscoveryProfile & {
  location_visible: boolean
  website_url?: string | null
  cover_image_url?: string | null
  interests_visible: boolean
  visibility: 'public' | 'connections' | 'private'
  actions: { can_report: boolean; can_block: boolean }
}

export type DiscoveryCategoryCount = { category: string; interests_count: number }

export type DiscoveryOverview = {
  discovery_enabled: boolean
  show_in_recommendations: boolean
  counts: {
    discoverable_people: number
    recommended_people: number
    active_interests: number
    my_interests: number
    hidden_profiles: number
  }
  categories: DiscoveryCategoryCount[]
}

export type DiscoveryInterestOption = DiscoveryInterestRef & { people_count: number }
export type DiscoveryInterestGroup = { category: string; interests: DiscoveryInterestOption[] }

/** Canonical discovery consent preferences (discovery_preferences table). */
export type DiscoveryPreferences = {
  discovery_enabled: boolean
  show_in_recommendations: boolean
  preferred_interest_categories: string[]
}

export type PeopleSort = 'relevance' | 'recently_joined' | 'username'
export type PeoplePerPage = 10 | 20 | 50
export type FeedbackType = 'not_interested' | 'hide_profile'

/** Filter state for the people explorer; mirrors GET /discovery/people. */
export type PeopleFilters = {
  search: string
  interest_ids: number[]
  sort: PeopleSort
  page: number
  per_page: PeoplePerPage
}

export const PEOPLE_PER_PAGE_OPTIONS = [10, 20, 50] as const
export const PEOPLE_SORTS = ['relevance', 'recently_joined', 'username'] as const

export type PaginationMeta = { current_page: number; last_page: number; per_page: number; total: number }
export type PeoplePage = { data: DiscoveryProfile[]; meta: PaginationMeta }
