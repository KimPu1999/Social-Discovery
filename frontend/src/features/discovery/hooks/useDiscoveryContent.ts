import { useQuery } from '@tanstack/react-query'
import { discoveryContentApi } from '../api/discoveryContentApi'

export const discoveryContentKeys = {
  all: ['discovery-content'] as const,
  feed: (type: string) => [...discoveryContentKeys.all, 'feed', type] as const,
  trending: () => [...discoveryContentKeys.all, 'trending'] as const,
  saved: () => [...discoveryContentKeys.all, 'saved'] as const,
}

export function useDiscoveryFeed(enabled = true) {
  return useQuery({ queryKey: discoveryContentKeys.feed('for_you'), queryFn: () => discoveryContentApi.feed('for_you'), staleTime: 20_000, enabled })
}

export function useDiscoveryTrending(enabled = true) {
  return useQuery({ queryKey: discoveryContentKeys.trending(), queryFn: discoveryContentApi.trending, staleTime: 60_000, enabled })
}

export function useDiscoverySaved(enabled = true) {
  return useQuery({ queryKey: discoveryContentKeys.saved(), queryFn: discoveryContentApi.saved, staleTime: 20_000, enabled })
}
