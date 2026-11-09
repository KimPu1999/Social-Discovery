import { api, csrf } from './api'
import type { User } from '../types'

export type AuthResponse = { user: User; email_verified?: boolean; message?: string }
export const authService = {
  async me() { const { data } = await api.get<AuthResponse>('/auth/me'); return data },
  async login(payload: { email: string; password: string; remember: boolean }) { await csrf(); const { data } = await api.post<AuthResponse>('/auth/login', payload); return data },
  async register(payload: { name: string; username: string; email: string; password: string; password_confirmation: string; terms_accepted: boolean; privacy_accepted: boolean }) { await csrf(); const { data } = await api.post<AuthResponse>('/auth/register', payload); return data },
  async logout() { const { data } = await api.post<{ message: string }>('/auth/logout'); return data },
  async forgotPassword(email: string) { await csrf(); const { data } = await api.post<{ message: string }>('/auth/forgot-password', { email }); return data },
  async resetPassword(payload: { token: string; email: string; password: string; password_confirmation: string }) { await csrf(); const { data } = await api.post<{ message: string }>('/auth/reset-password', payload); return data },
  async resendVerification() { const { data } = await api.post<{ message: string }>('/auth/email/resend'); return data },
  async updatePassword(payload: { current_password: string; password: string; password_confirmation: string }) { await csrf(); const { data } = await api.put<{ message: string }>('/auth/password', payload); return data },
  async updateEmail(payload: { email: string; password: string }) { await csrf(); const { data } = await api.put<{ message: string }>('/auth/email', payload); return data },
  async deactivate(password: string) { await csrf(); const { data } = await api.post<{ message: string }>('/account/deactivate', { password }); return data },
  async deleteRequest(password: string) { await csrf(); const { data } = await api.post<{ message: string }>('/account/delete-request', { password }); return data },
}
