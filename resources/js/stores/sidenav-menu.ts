import { defineStore } from 'pinia'
import { ref } from 'vue'

export type SidenavOpenMenuKeySource = 'route' | 'user'

export const useSidenavMenuStore = defineStore(
  'sidenav-menu',
  () => {
    const openMenuKey = ref<string | null>(null)
    /** Sobald der Nutzer das Top-Akkordeon bedient hat, gewinnt der gespeicherte Zustand beim Reload. */
    const lastAccordionInteractionAt = ref<number | null>(null)
    /** Explizit gesetzte Zustände verschachtelter Zweige (slug → offen). */
    const nestedOpen = ref<Record<string, boolean>>({})

    function setOpenMenuKey(key: string | null, source: SidenavOpenMenuKeySource = 'user'): void {
      openMenuKey.value = key
      if (source === 'user') {
        lastAccordionInteractionAt.value = Date.now()
      }
    }

    function setNestedOpen(slug: string, open: boolean): void {
      nestedOpen.value = { ...nestedOpen.value, [slug]: open }
    }

    function clearNestedOpen(slug: string): void {
      if (!(slug in nestedOpen.value)) {
        return
      }
      const next = { ...nestedOpen.value }
      delete next[slug]
      nestedOpen.value = next
    }

    return {
      openMenuKey,
      lastAccordionInteractionAt,
      nestedOpen,
      setOpenMenuKey,
      setNestedOpen,
      clearNestedOpen,
    }
  },
  { persist: true },
)
