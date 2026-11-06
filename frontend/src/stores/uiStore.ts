import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import type { Theme } from '../types'
import type { Locale } from '../i18n'

type UiState = {
  sidebarOpen: boolean
  theme: Theme
  locale: Locale
  toggleSidebar: () => void
  setTheme: (theme: Theme) => void
  setLocale: (locale: Locale) => void
}

export const useUiStore = create<UiState>()(persist((set) => ({
  sidebarOpen: true,
  theme: 'system',
  locale: 'en',
  toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),
  setTheme: (theme) => set({ theme }),
  setLocale: (locale) => set({ locale }),
}), { name: 'social-discovery-ui' }))
