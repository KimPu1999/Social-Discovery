export const copy = {
  en: { appName: 'Social Discovery', tagline: 'Find your people.', nav: 'Explore with intention.' },
  my: { appName: 'Social Discovery', tagline: 'သင့်လူတွေကို ရှာဖွေပါ။', nav: 'ရည်ရွယ်ချက်ရှိရှိ ရှာဖွေပါ။' },
} as const

export type Locale = keyof typeof copy
