import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Sidebar-Zweige: mehrere gleichzeitig offen möglich.
 * Standard: alles offen — nur explizit zugeklappte Slugs werden gespeichert.
 */
export const useSidenavMenuStore = defineStore(
  'sidenav-sidebar-branches',
  () => {
    const topLevelClosed = ref<Record<string, true>>({})
    const nestedClosed = ref<Record<string, true>>({})

    function isTopLevelBranchOpen(slug: string): boolean {
      return topLevelClosed.value[slug] !== true
    }

    function isNestedBranchOpen(slug: string): boolean {
      return nestedClosed.value[slug] !== true
    }

    function toggleTopLevelBranch(slug: string): void {
      if (topLevelClosed.value[slug] === true) {
        const { [slug]: _, ...rest } = topLevelClosed.value
        topLevelClosed.value = rest
      } else {
        topLevelClosed.value = { ...topLevelClosed.value, [slug]: true }
      }
    }

    function toggleNestedBranch(slug: string): void {
      if (nestedClosed.value[slug] === true) {
        const { [slug]: _, ...rest } = nestedClosed.value
        nestedClosed.value = rest
      } else {
        nestedClosed.value = { ...nestedClosed.value, [slug]: true }
      }
    }

    return {
      topLevelClosed,
      nestedClosed,
      isTopLevelBranchOpen,
      isNestedBranchOpen,
      toggleTopLevelBranch,
      toggleNestedBranch,
    }
  },
  {
    persist: {
      pick: ['topLevelClosed', 'nestedClosed'],
    },
  },
)
