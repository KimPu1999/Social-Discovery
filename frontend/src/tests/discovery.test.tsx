import { cleanup, render, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { DiscoverPage } from '../features/discovery/pages/DiscoverPage'
import { PeopleExplorerPage } from '../features/discovery/pages/PeopleExplorerPage'
import { DiscoveryProfilePage } from '../features/discovery/pages/DiscoveryProfilePage'
import { DiscoverySettingsPage } from '../features/discovery/pages/DiscoverySettingsPage'
import { discoveryApi } from '../features/discovery/api/discoveryApi'
import { useToastStore } from '../components/ui'
import { useUiStore } from '../stores/uiStore'
import { connectionsApi } from '../features/connections/api/connectionsApi'
import type { DiscoveryProfile } from '../features/discovery/types'

vi.mock('../features/discovery/api/discoveryApi', () => ({
  discoveryApi: {
    overview: vi.fn(),
    people: vi.fn(),
    recommended: vi.fn(),
    interests: vi.fn(),
    profile: vi.fn(),
    preferences: vi.fn(),
    updatePreferences: vi.fn(),
    giveFeedback: vi.fn(),
    undoFeedback: vi.fn(),
  },
}))

const connectedRelationship = {
  is_following: false,
  is_followed_by: false,
  is_connected: false,
  request_status: 'none' as const,
  can_follow: true,
  can_connect: true,
  is_blocked_by_me: false,
}

vi.mock('../features/connections/api/connectionsApi', () => ({
  connectionsApi: {
    relationship: vi.fn(),
    followers: vi.fn(),
    following: vi.fn(),
    mutualConnections: vi.fn(),
    sendRequest: vi.fn(),
    block: vi.fn(),
    unblock: vi.fn(),
    follow: vi.fn(),
    unfollow: vi.fn(),
  },
}))

const sharedProfile: DiscoveryProfile = {
  id: 7,
  username: 'aria',
  display_name: 'Aria Sun',
  bio: 'Loves music and slow travel.',
  avatar_url: null,
  joined_at: new Date('2026-01-10').toISOString(),
  interests: [
    { id: 1, name: 'Music', slug: 'music', category: 'Creative', shared_with_viewer: true },
    { id: 2, name: 'Travel', slug: 'travel', category: 'Lifestyle', shared_with_viewer: true },
    { id: 3, name: 'Art', slug: 'art', category: 'Creative' },
  ],
  match: { score: 50, shared_count: 2, shared_interests: [{ id: 1, name: 'Music', slug: 'music', category: 'Creative' }, { id: 2, name: 'Travel', slug: 'travel', category: 'Lifestyle' }] },
}

const overviewEnabled = {
  discovery_enabled: true,
  show_in_recommendations: true,
  counts: { discoverable_people: 3, recommended_people: 1, active_interests: 12, my_interests: 2, hidden_profiles: 0 },
  categories: [{ category: 'Creative', interests_count: 6 }, { category: 'Lifestyle', interests_count: 6 }],
}

const interestGroups = [
  { category: 'Creative', interests: [{ id: 1, name: 'Music', slug: 'music', category: 'Creative', people_count: 2 }] },
  { category: 'Lifestyle', interests: [{ id: 2, name: 'Travel', slug: 'travel', category: 'Lifestyle', people_count: 1 }] },
]

const renderPage = (node: React.ReactElement, entry = '/') => {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[entry]}>{node}</MemoryRouter>
    </QueryClientProvider>,
  )
}

/** Full router for pages that rely on route params. */
const renderRoutes = (entry: string) => {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[entry]}>
        <Routes>
          <Route path="/people" element={<><PeopleExplorerPage /><RouteProbe /></>} />
          <Route path="/people/:username" element={<DiscoveryProfilePage />} />
          <Route path="/discover" element={<DiscoverPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

/** Exposes the current router location for URL-sync assertions. */
function RouteProbe() {
  const location = useLocation()
  return <span data-testid="location-bar">{location.pathname}{location.search}</span>
}

beforeEach(() => {
  cleanup()
  useUiStore.setState({ theme: 'light', locale: 'en', sidebarOpen: true })
  vi.mocked(discoveryApi.overview).mockResolvedValue(overviewEnabled)
  vi.mocked(discoveryApi.recommended).mockResolvedValue([sharedProfile])
  vi.mocked(discoveryApi.interests).mockResolvedValue(interestGroups)
  vi.mocked(discoveryApi.people).mockResolvedValue({ data: [sharedProfile, { ...sharedProfile, id: 8, username: 'bo', display_name: 'Bo Keep', match: null }], meta: { current_page: 1, last_page: 1, per_page: 10, total: 2 } })
  vi.mocked(discoveryApi.preferences).mockResolvedValue({ discovery_enabled: false, show_in_recommendations: true, preferred_interest_categories: [] })
  vi.mocked(discoveryApi.giveFeedback).mockResolvedValue('Profile hidden.')
  vi.mocked(discoveryApi.undoFeedback).mockResolvedValue('Profile unhidden.')
  vi.mocked(connectionsApi.relationship).mockResolvedValue({
    relationship: connectedRelationship,
    counts: { followers: 3, following: 2, connections: 1 },
    mutual: { count: 1, preview: [{ id: 8, username: 'bo', display_name: 'Bo Keep' }] },
  })
  vi.mocked(connectionsApi.block).mockResolvedValue({ message: 'Blocked.', blocked: true })
  vi.mocked(connectionsApi.follow).mockResolvedValue({ message: 'ok', followed: true, relationship: { ...connectedRelationship, is_following: true } })
  vi.mocked(connectionsApi.sendRequest).mockResolvedValue({ message: 'ok', created: true, relationship: { ...connectedRelationship, request_status: 'outgoing_pending' as const } })
})

afterEach(() => { vi.clearAllMocks() })

describe('discover page', () => {
  it('renders the overview summary, recommendations, and interest chips', async () => {
    const view = renderPage(<DiscoverPage />)
    expect(await view.findByText('Discover People')).toBeInTheDocument()
    await waitFor(() => expect(view.getAllByText('Discoverable people').length).toBeGreaterThan(0))
    expect(view.getByText('Recommended for you')).toBeInTheDocument()
    expect((await view.findAllByText('Aria Sun')).length).toBeGreaterThan(0)
    expect(view.getByText('Music · 2')).toBeInTheDocument()
  })

  it('shows the disabled state instead of results when discovery is off', async () => {
    vi.mocked(discoveryApi.overview).mockResolvedValue({ ...overviewEnabled, discovery_enabled: false })
    const view = renderPage(<DiscoverPage />)
    await view.findByText('Discovery is off')
    expect(view.getByText('Enable discovery to see recommended people and explore profiles.')).toBeInTheDocument()
    await waitFor(() => expect(discoveryApi.recommended).not.toHaveBeenCalled())
  })

  it('renders the connect button as clearly unavailable', async () => {
    const view = renderPage(<DiscoverPage />)
    await view.findByText('Aria Sun')
    const connect = view.getAllByRole('button', { name: 'Connect' })[0]
    expect(connect).toBeDisabled()
  })
})

describe('people explorer', () => {
  it('marks the explore entry points and renders result counts from real data', async () => {
    const view = renderRoutes('/people')
    expect(await view.findByText('Aria Sun')).toBeInTheDocument()
    expect(view.getByText('2 people')).toBeInTheDocument()
  })

  it('sends the debounced search to the API and syncs the URL query', async () => {
    const user = userEvent.setup()
    const view = renderRoutes('/people')
    await view.findByText('Aria Sun')
    const input = view.getAllByPlaceholderText('Search by name or username')[0]
    await user.type(input, 'aria')
    await waitFor(() => {
      const last = vi.mocked(discoveryApi.people).mock.calls.at(-1)
      expect(last?.[0].search).toBe('aria')
    }, { timeout: 3000 })
    // URL query synchronization (q=aria lands in the address after debounce).
    await waitFor(() => {
      expect(document.querySelector('[data-testid="location-bar"]')?.textContent).toContain('q=aria')
    }, { timeout: 3000 })
  })

  it('applies a sort through the toolbar', async () => {
    const user = userEvent.setup()
    const view = renderRoutes('/people')
    await view.findByText('Aria Sun')
    await user.click(view.getAllByText('Sort')[0])
    await user.click(within(view.getByRole('menu')).getByText('A–Z by username'))
    await waitFor(() => {
      const last = vi.mocked(discoveryApi.people).mock.calls.at(-1)
      expect(last?.[0].sort).toBe('username')
    })
  })

  it('paginates with the pagination control', async () => {
    vi.mocked(discoveryApi.people).mockResolvedValue({
      data: Array.from({ length: 10 }, (_, index) => ({ ...sharedProfile, id: index + 1, username: `user-${index}`, display_name: `User ${index}` })),
      meta: { current_page: 1, last_page: 2, per_page: 10, total: 15 },
    })
    const user = userEvent.setup()
    const view = renderRoutes('/people')
    await view.findByText('User 0')
    await user.click(view.getByRole('button', { name: 'Next page' }))
    await waitFor(() => {
      const last = vi.mocked(discoveryApi.people).mock.calls.at(-1)
      expect(last?.[0].page).toBe(2)
    })
  })

  it('shows the matching-interests empty state when filtered results are empty', async () => {
    vi.mocked(discoveryApi.people).mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } })
    const view = renderRoutes('/people?interests=1')
    await view.findByText('No matches for these interests')
  })

  it('shows an error state with retry when the API fails', async () => {
    vi.mocked(discoveryApi.people).mockRejectedValue(new Error('network down'))
    const view = renderRoutes('/people')
    await view.findByText('Discovery could not load')
    expect(view.getByText('Try again')).toBeInTheDocument()
  })

  it('hides a profile through the feedback menu and confirms with a toast', async () => {
    const user = userEvent.setup()
    const view = renderRoutes('/people')
    await view.findByText('Aria Sun')
    await user.click(view.getAllByRole('button', { name: 'Profile options' })[0])
    await user.click(within(view.getAllByRole('menu')[0]).getByText('Hide profile'))
    await waitFor(() => {
      expect(discoveryApi.giveFeedback).toHaveBeenCalledWith(7, 'hide_profile')
    })
    // The confirmation toast is pushed to the global toast store (viewport renders in App layout).
    await waitFor(() => {
      expect(useToastStore.getState().toasts.some((toast) => toast.title === 'Profile hidden')).toBe(true)
    })
  })
})

describe('profile preview', () => {
  it('renders the privacy-safe public profile with live social state', async () => {
    vi.mocked(discoveryApi.profile).mockResolvedValue({
      ...sharedProfile,
      relationship: connectedRelationship,
      location_visible: false,
      cover_image_url: null,
      website_url: null,
      interests_visible: true,
      visibility: 'public',
      actions: { can_report: false, can_block: true },
    })
    const view = renderRoutes('/people/aria')
    expect(await view.findByText('Aria Sun')).toBeInTheDocument()
    expect(view.getByText('Public profile')).toBeInTheDocument()

    // Real counts from the relationship endpoint; nothing invented on the client.
    expect(await view.findByText('3')).toBeInTheDocument()
    // Report stays deliberately unavailable until the safety layer ships.
    expect(view.getByRole('button', { name: 'Report' })).toBeDisabled()
  })

  it('keeps the unfollow action available when the server reports an existing follow', async () => {
    const following = { ...connectedRelationship, is_following: true, can_follow: false }
    vi.mocked(discoveryApi.profile).mockResolvedValue({
      ...sharedProfile,
      relationship: following,
      location_visible: false,
      cover_image_url: null,
      website_url: null,
      interests_visible: true,
      visibility: 'public',
      actions: { can_report: false, can_block: true },
    })
    vi.mocked(connectionsApi.relationship).mockResolvedValue({ relationship: following, counts: { followers: 3, following: 2, connections: 1 }, mutual: null })

    const view = renderRoutes('/people/aria')
    expect(await view.findByRole('button', { name: 'Following' })).toBeInTheDocument()
  })

  it('blocks the member after confirmation', async () => {
    const user = userEvent.setup()
    vi.mocked(discoveryApi.profile).mockResolvedValue({
      ...sharedProfile,
      relationship: connectedRelationship,
      location_visible: false,
      cover_image_url: null,
      website_url: null,
      interests_visible: true,
      visibility: 'public',
      actions: { can_report: false, can_block: true },
    })
    const view = renderRoutes('/people/aria')
    await view.findByText('Aria Sun')
    await user.click(view.getByRole('button', { name: 'Block' }))
    // The confirm dialog reuses the "Block" wording; scope to the dialog.
    const dialog = await view.findByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: 'Block' }))
    await waitFor(() => expect(vi.mocked(connectionsApi.block).mock.calls[0]?.[0]).toBe(7))
  })

  it('renders the uniform unavailable state for non-eligible profiles', async () => {
    vi.mocked(discoveryApi.profile).mockRejectedValue({ response: { status: 404 } })
    const view = renderRoutes('/people/vanished')
    expect(await view.findByText('Profile unavailable')).toBeInTheDocument()
    // No detail about privacy vs. missing vs. blocked.
    expect(view.queryByText(/private or deleted/i)).toBeNull()
  })
})

describe('discovery settings', () => {
  it('persists enablement and recommendations through the preferences API', async () => {
    vi.mocked(discoveryApi.updatePreferences).mockResolvedValue({ discovery_enabled: true, show_in_recommendations: true, preferred_interest_categories: [] })
    const user = userEvent.setup()
    const view = renderPage(<DiscoverySettingsPage />)
    await view.findByText('Enable discovery', { selector: 'label span' }).catch(() => undefined)
    const enable = await view.findByLabelText('Enable discovery')
    await user.click(enable)
    await waitFor(() => {
      const firstCall = vi.mocked(discoveryApi.updatePreferences).mock.calls[0]?.[0]
      expect(firstCall).toEqual(expect.objectContaining({ discovery_enabled: true }))
    })
  })

  it('explains the effect of discovery being off', async () => {
    const view = renderPage(<DiscoverySettingsPage />)
    expect(await view.findByText('Discovery is currently off')).toBeInTheDocument()
    expect(view.getByText('Your profile is excluded from other people’s discovery results, and recommendations are hidden for you.')).toBeInTheDocument()
  })
})

describe('localization', () => {
  it('renders discovery copy in Myanmar when locale is MM', async () => {
    useUiStore.setState({ locale: 'my' })
    const view = renderRoutes('/people')
    expect((await view.findAllByText('လူများ')).length).toBeGreaterThan(0)
    expect(view.getByText('စိတ်ဝင်စားမှုတူသူများနှင့် တွေ့ဆုံရန် စနစ်တကျ လမ်းညွှန်။')).toBeInTheDocument()
  })
})
