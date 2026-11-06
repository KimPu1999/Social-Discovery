import { api } from './api'
import type { Interest } from '../types'
export const interestService = { async list() { const { data } = await api.get<{ interests: Interest[] }>('/interests'); return data.interests } }
