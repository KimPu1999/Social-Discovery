import { render } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { describe, expect, it, vi } from 'vitest'
import { waitFor } from '@testing-library/react'
import App from '../App'

vi.mock('../services/authService', () => ({ authService: { me: () => Promise.resolve({ user: { id: 1, name: 'Kim', username: 'kim', role: 'user', status: 'active' } }) } }))

describe('application routes', () => {
  it('renders the public landing page', () => {
    const view = render(<QueryClientProvider client={new QueryClient()}><MemoryRouter initialEntries={['/']}><App /></MemoryRouter></QueryClientProvider>)
    expect(view.getByText('Find the people who make your world feel bigger.')).toBeInTheDocument()
  })
  it('renders an authenticated workspace route', async () => {
    const view = render(<QueryClientProvider client={new QueryClient()}><MemoryRouter initialEntries={['/dashboard']}><App /></MemoryRouter></QueryClientProvider>)
    await waitFor(() => expect(view.getByText('Good morning, Kim.')).toBeInTheDocument())
  })
})
