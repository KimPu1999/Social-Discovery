import { cleanup, render, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from '../App'
import { useUiStore } from '../stores/uiStore'
import { authService } from '../services/authService'

const authenticatedUser = { id: 1, name: 'Kim', username: 'kim', role: 'user', status: 'active', email_verified: true }

vi.mock('../services/authService', () => ({
  authService: {
    me: vi.fn(() => Promise.reject(new Error('unauthenticated'))),
    login: vi.fn(),
    logout: vi.fn(() => Promise.resolve({ message: 'ok' })),
  },
}))

const renderApp = (entry: string) => render(
  <QueryClientProvider client={new QueryClient()}><MemoryRouter initialEntries={[entry]}><App /></MemoryRouter></QueryClientProvider>,
)

const mockAuthenticated = () => {
  vi.mocked(authService.me).mockResolvedValue({ user: authenticatedUser })
}

beforeEach(() => {
  cleanup()
  useUiStore.setState({ theme: 'light', locale: 'en', sidebarOpen: true })
  document.documentElement.dataset.theme = 'light'
  vi.mocked(authService.me as unknown as ReturnType<typeof vi.fn>).mockRejectedValue(new Error('unauthenticated'))
})

describe('application routes', () => {
  it('renders the public landing page', async () => {
    const view = renderApp('/')
    expect(await view.findByText('Find the people who make your world feel bigger.')).toBeInTheDocument()
  })

  it('renders an authenticated workspace route', async () => {
    mockAuthenticated()
    const view = renderApp('/dashboard')
    await waitFor(() => expect(view.getByText(/, Kim\./)).toBeInTheDocument())
  })

  it('redirects unauthenticated visitors away from member routes', async () => {
    const view = renderApp('/dashboard')
    await waitFor(() => expect(view.getByText('Sign in to your space')).toBeInTheDocument())
  })

  it('restricts administration routes for regular members', async () => {
    mockAuthenticated()
    const view = renderApp('/admin/overview')
    await waitFor(() => expect(view.getByText('Access restricted')).toBeInTheDocument())
  })

  it('renders the localized sign-in page', async () => {
    const view = renderApp('/login')
    expect(await view.findByText('Sign in to your space')).toBeInTheDocument()
    expect(view.getByText('Email or username')).toBeInTheDocument()
    expect(view.getByText('Password')).toBeInTheDocument()
  })
})

describe('design system and layout behavior', () => {
  it('switches the application language between English and Myanmar', async () => {
    const user = userEvent.setup()
    const view = renderApp('/')
    const header = await view.findByRole('banner')
    await waitFor(() => expect(within(header).getAllByText('Home').length).toBeGreaterThan(0))
    await user.click(within(header).getByRole('button', { name: 'EN' }))
    await user.click(within(view.getByRole('menu')).getByText('မြန်မာ'))
    expect(within(header).getAllByText('ပင်မ').length).toBeGreaterThan(0)
  })

  it('cycles light, dark, and system themes', async () => {
    const user = userEvent.setup()
    const view = renderApp('/')
    const header = await view.findByRole('banner')
    await waitFor(() => expect(within(header).getAllByText('Home').length).toBeGreaterThan(0))
    await user.click(within(header).getByRole('button', { name: /Change theme/ }))
    expect(document.documentElement.dataset.theme).toBe('dark')
    await user.click(within(header).getByRole('button', { name: /Change theme/ }))
    expect(useUiStore.getState().theme).toBe('system')
  })

  it('shows the member sidebar groups for authorized navigation', async () => {
    mockAuthenticated()
    const view = renderApp('/dashboard')
    await waitFor(() => expect(view.getByRole('navigation', { name: 'Member navigation' })).toBeInTheDocument())
    const nav = view.getByRole('navigation', { name: 'Member navigation' })
    expect(within(nav).getByText('Dashboard')).toBeInTheDocument()
    expect(within(nav).getByText('Messages')).toBeInTheDocument()
    expect(within(nav).getByText('Settings')).toBeInTheDocument()
  })

  it('renders the public footer columns', async () => {
    const view = renderApp('/')
    await view.findByRole('banner')
    const footer = await view.findByRole('contentinfo', {}, { timeout: 3000 })
    expect(within(footer).getByText('Platform')).toBeInTheDocument()
    expect(within(footer).getByText('Community')).toBeInTheDocument()
    expect(within(footer).getByText('Support')).toBeInTheDocument()
    expect(within(footer).getByText('Legal')).toBeInTheDocument()
  })
})
