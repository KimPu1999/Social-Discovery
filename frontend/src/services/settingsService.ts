import { api } from './api'
import type { PrivacySettings, Settings } from '../types'
export const settingsService = {
  async get() { const { data } = await api.get<{ settings: Settings }>('/settings'); return data.settings },
  async update(payload: Partial<Settings>) { const { data } = await api.put<{ settings: Settings }>('/settings', payload); return data.settings },
  async getPrivacy() { const { data } = await api.get<{ privacy: PrivacySettings }>('/settings/privacy'); return data.privacy },
  async updatePrivacy(payload: Partial<PrivacySettings>) { const { data } = await api.put<{ privacy: PrivacySettings }>('/settings/privacy', payload); return data.privacy },
}
