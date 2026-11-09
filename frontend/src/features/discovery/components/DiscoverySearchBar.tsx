import { useEffect, useRef, useState } from 'react'
import { SearchBar } from '../../../components/ui'
import { useDebouncedValue } from '../hooks/useDiscoveryFilters'
import { useTranslation } from '../../../layouts/AppLayouts'

type DiscoverySearchBarProps = {
  value: string
  onDebouncedChange: (value: string) => void
  delay?: number
  placeholder?: string
}

/**
 * Search input with built-in debouncing so keystrokes don't fire a request
 * per character. The displayed value is local state; changes propagate after
 * `delay` ms of quiet. The callback is kept in a ref so consumers can pass
 * inline handlers without retriggering the debounce effect.
 */
export function DiscoverySearchBar({ value, onDebouncedChange, delay = 300, placeholder }: DiscoverySearchBarProps) {
  const { t } = useTranslation()
  const [local, setLocal] = useState(value)
  const debounced = useDebouncedValue(local, delay)
  const callbackRef = useRef(onDebouncedChange)
  // Keep the latest callback without retriggering the debounce effect.
  useEffect(() => { callbackRef.current = onDebouncedChange })

  // External value change (e.g. URL sync) wins over local edits.
  const [prevValue, setPrevValue] = useState(value)
  if (value !== prevValue) {
    setPrevValue(value)
    setLocal(value)
  }
  useEffect(() => { callbackRef.current(debounced) }, [debounced])

  return (
    <SearchBar
      value={local}
      onChange={setLocal}
      onClear={() => setLocal('')}
      placeholder={placeholder ?? t.discovery.searchPlaceholder}
      className="discovery-search"
    />
  )
}
