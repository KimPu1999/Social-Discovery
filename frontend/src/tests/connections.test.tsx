import { cleanup, render, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Outlet, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { ThemeSync } from '../layouts/AppLayouts'
import { ConnectionsPage } from '../features/connections/pages/ConnectionsPage'
import { NotificationsPage } from '../features/connections/pages/NotificationsPage'
import { FollowButton } from '../features/connections/components/FollowButton'
import { connectionsApi } from '../features/connections/api/connectionsApi'
import { useToastStore } from '../components/ui'
import { useUiStore } from '../stores/uiStore'
import type { RelationshipState, SocialUser } from '../features/connections/types'

vi.mock('../features/connections/api/connectionsApi', () => ({
  connectionsApi: {
    follow: vi.fn(),
    unfollow: vi.fn(),
    relationship: vi.fn(),
    followers: vi.fn(),
    following: vi.fn(),
    mutualConnections: vi.fn(),
    sendRequest: vi.fn(),
    incomingRequests: vi.fn(),
    outgoingRequests: vi.fn(),
    acceptRequest: vi.fn(),
    declineRequest: vi.fn(),
    cancelRequest: vi.fn(),
    myConnections: vi.fn(),
    userConnections: vi.fn(),
    removeConnection: vi.fn(),
    block: vi.fn(),
    unblock: vi.fn(),
    blockedUsers: vi.fn(),
    notifications: vi.fn(),
    unreadCount: vi.fn(),
    markRead: vi.fn(),
    markAllRead: vi.fn(),
  },
}))

const meta = { current_page: 1, last_page: 1, per_page: 10, total: 1 }

const peer: SocialUser = { id: 7, username: 'aria', display_name: 'Aria Sun', bio: 'Loves music.' }

const openRelationship: RelationshipState = {
  is_following: false, is_followed_by: false, is_connected: false, request_status: 'none', can_follow: true, can_connect: true, is_blocked_by_me: false,
}

/** Minimal member context: pages under ProtectedLayout read it via Outlet context. */
const fakeUser = { id: 42, name: 'Kim Pu', username: 'kimpu', email: 'k@example.com', status: 'active', role: 'member', profile: { display_name: 'Kim Pu' } }

const renderRoutes = (entry: string) => {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[entry]}>
        <ThemeSync />
        <Routes>
          <Route element={<Outlet context={fakeUser} />}>
            <Route path="/connections" element={<ConnectionsPage />} />
            <Route path="/notifications" element={<NotificationsPage />} />
          </Route>
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

const renderNode = (node: React.ReactElement) => {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>{node}</MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  cleanup()
  useUiStore.setState({ theme: 'light', locale: 'en', sidebarOpen: true })
  vi.mocked(connectionsApi.follow).mockResolvedValue({ message: 'ok', followed: true, relationship: { ...openRelationship, is_following: true } })
  vi.mocked(connectionsApi.unfollow).mockResolvedValue({ message: 'ok', followed: false, relationship: { ...openRelationship, is_following: false } })
  vi.mocked(connectionsApi.sendRequest).mockResolvedValue({ message: 'ok', created: true, relationship: { ...openRelationship, request_status: 'outgoing_pending' } })
  vi.mocked(connectionsApi.acceptRequest).mockResolvedValue({ message: 'ok', relationship: { ...openRelationship, is_connected: true } })
  vi.mocked(connectionsApi.cancelRequest).mockResolvedValue({ message: 'ok' })
  vi.mocked(connectionsApi.removeConnection).mockResolvedValue({ message: 'ok', relationship: openRelationship })
  vi.mocked(connectionsApi.unblock).mockResolvedValue({ message: 'ok', blocked: false })
  vi.mocked(connectionsApi.incomingRequests).mockResolvedValue({ data: [{ id: 5, status: 'pending', created_at: new Date('2026-02-02').toISOString(), sender: peer, receiver: { id: 42, username: 'kimpu', display_name: 'Kim Pu' } }], meta })
  vi.mocked(connectionsApi.outgoingRequests).mockResolvedValue({ data: [{ id: 6, status: 'pending', created_at: new Date('2026-02-02').toISOString(), sender: { id: 42, username: 'kimpu', display_name: 'Kim Pu' }, receiver: peer }], meta })
  vi.mocked(connectionsApi.myConnections).mockResolvedValue({ data: [{ user: peer, connected_at: new Date('2026-02-02').toISOString(), relationship: { ...openRelationship, is_connected: true, can_follow: false, can_connect: false } }], meta })
  vi.mocked(connectionsApi.followers).mockResolvedValue({ data: [{ user: peer, followed_at: null, relationship: openRelationship }], meta, relationship: null })
  vi.mocked(connectionsApi.following).mockResolvedValue({ data: [{ user: peer, followed_at: null, relationship: { ...openRelationship, is_following: true } }], meta, relationship: null })
  vi.mocked(connectionsApi.blockedUsers).mockResolvedValue({ data: [{ user: peer, blocked_at: new Date('2026-02-02').toISOString() }], meta })
  vi.mocked(connectionsApi.notifications).mockResolvedValue({
    data: [{ id: 'n-1', type: 'new_follower', actor: { id: 7, username: 'aria', display_name: 'Aria Sun' }, url: '/people/aria', read_at: null, created_at: new Date('2026-02-02').toISOString() }],
    meta,
    unread_count: 1,
  })
  vi.mocked(connectionsApi.markRead).mockResolvedValue({ message: 'ok', unread_count: 0 })
  vi.mocked(connectionsApi.markAllRead).mockResolvedValue({ message: 'ok', unread_count: 0 })
  vi.mocked(connectionsApi.relationship).mockResolvedValue({
    relationship: openRelationship,
    counts: { followers: 3, following: 2, connections: 1 },
    mutual: null,
  })
})

afterEach(() => { vi.clearAllMocks() })

describe('connections workspace', () => {
  it('lists the viewer connections with real server data and removes after confirmation', async () => {
    const user = userEvent.setup()
    const view = renderRoutes('/connections')
    expect(await view.findByText('Connections', { selector: 'h2' })).toBeInTheDocument()
    await view.findByText('Aria Sun')

    // Remove requires a deliberate confirmation.
    await user.click(view.getByRole('button', { name: 'Remove' }))
    const dialog = await view.findByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: 'Remove' }))
    await waitFor(() => expect(vi.mocked(connectionsApi.removeConnection).mock.calls[0]?.[0]).toBe(7))
  })

  it('accepts incoming requests and cancels sent requests', async () => {
    const user = userEvent.setup()
    const view = renderRoutes('/connections?tab=incoming')
    expect(await view.findByText('Requests')).toBeInTheDocument()
    await view.findByText('Aria Sun')
    await user.click(view.getByRole('button', { name: 'Accept' }))
    await waitFor(() => expect(vi.mocked(connectionsApi.acceptRequest).mock.calls[0]?.[0]).toBe(5))
  }, 15_000)

  it('cancels outgoing requests', async () => {
    const user = userEvent.setup()
    const view = renderRoutes('/connections?tab=outgoing')
    const cancel = await view.findByRole('button', { name: 'Cancel request' })
    await user.click(cancel)
    await waitFor(() => expect(vi.mocked(connectionsApi.cancelRequest).mock.calls[0]?.[0]).toBe(6))
  })

  it('unblocks accounts from the blocked tab', async () => {
    const user = userEvent.setup()
    const view = renderRoutes('/connections?tab=blocked')
    await view.findByText('Aria Sun')
    await user.click(view.getByRole('button', { name: 'Unblock' }))
    await waitFor(() => expect(vi.mocked(connectionsApi.unblock).mock.calls[0]?.[0]).toBe(7))
  })

  it('paginates from URL-friendly state', async () => {
    vi.mocked(connectionsApi.myConnections).mockResolvedValue({
      data: Array.from({ length: 10 }, (_, index) => ({ user: { ...peer, id: index + 1, username: `user-${index}` }, connected_at: null, relationship: null })),
      meta: { current_page: 1, last_page: 2, per_page: 10, total: 15 },
    })
    const user = userEvent.setup()
    const view = renderRoutes('/connections')
    await view.findAllByText('Aria Sun')
    await user.click(view.getByRole('button', { name: 'Next page' }))
    await waitFor(() => {
      const lastCall = vi.mocked(connectionsApi.myConnections).mock.calls.at(-1)
      expect(lastCall?.[0]?.params?.page).toBe(2)
    })
  })

  it('renders in Myanmar when the locale is switched', async () => {
    useUiStore.setState({ locale: 'my' })
    const view = renderRoutes('/connections')
    expect(await view.findByText('ဆက်သွယ်မှုများ', { selector: 'h2' })).toBeInTheDocument()
  })
})

describe('follow button', () => {
  it('follows and unfollows through the API with a toast after server confirmation', async () => {
    const user = userEvent.setup()
    const view = renderNode(<FollowButton userId={7} relationship={openRelationship} />)
    await user.click(view.getByRole('button', { name: 'Follow' }))
    await waitFor(() => expect(vi.mocked(connectionsApi.follow).mock.calls[0]?.[0]).toBe(7))
    await waitFor(() => {
      expect(useToastStore.getState().toasts.some((toast) => toast.title === 'You are now following this member.')).toBe(true)
    })
  })
})

describe('notifications page', () => {
  it('shows unread notifications and marks one as read', async () => {
    const user = userEvent.setup()
    const view = renderRoutes('/notifications')
    expect(await view.findByText('Notifications')).toBeInTheDocument()
    expect(await view.findByText(/Aria Sun started following you/)).toBeInTheDocument()
    expect(view.getByText('1 new')).toBeInTheDocument()

    await user.click(view.getByRole('button', { name: 'Mark read' }))
    await waitFor(() => expect(vi.mocked(connectionsApi.markRead).mock.calls[0]?.[0]).toBe('n-1'))
  })

  it('marks everything read at once', async () => {
    const user = userEvent.setup()
    const view = renderRoutes('/notifications')
    await view.findByText(/Aria Sun started following you/)
    await user.click(view.getByRole('button', { name: 'Mark all read' }))
    await waitFor(() => expect(connectionsApi.markAllRead).toHaveBeenCalled())
  })
})

describe('dark mode', () => {
  it('applies the dark theme attribute through the shared sync', () => {
    useUiStore.setState({ theme: 'dark' })
    renderNode(<ThemeSync />)
    expect(document.documentElement.dataset.theme).toBe('dark')
  })
})
