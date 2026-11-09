import '@testing-library/jest-dom/vitest'

// jsdom does not implement matchMedia; ThemeSync CSS-variable theme logic needs it.
if (!window.matchMedia) {
  window.matchMedia = (query: string): MediaQueryList => ({
    matches: query.includes('prefers-color-scheme: dark') ? false : false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  })
}

Element.prototype.scrollTo = Element.prototype.scrollTo || (() => {})
window.scrollTo = window.scrollTo || (() => {})
