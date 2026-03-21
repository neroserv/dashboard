import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

/**
 * Inertia teilt `brand` als camelCase; Seiten mit vollem Brand-Model (z. B. admin/brands/Edit) liefern snake_case.
 */
export type PageBrandLike = {
    logoUrl?: string | null;
    logoCollapsedUrl?: string | null;
    logo_url?: string | null;
    logo_collapsed_url?: string | null;
    authCardBgUrl?: string | null;
    auth_card_bg_url?: string | null;
};

export function brandMainLogoRaw(brand: PageBrandLike | null | undefined): string | null {
    if (!brand) {
        return null;
    }
    const v = brand.logoUrl ?? brand.logo_url;
    if (v === null || v === undefined) {
        return null;
    }
    const s = String(v).trim();

    return s === '' ? null : s;
}

export function brandCollapsedLogoRaw(brand: PageBrandLike | null | undefined): string | null {
    if (!brand) {
        return null;
    }
    const v = brand.logoCollapsedUrl ?? brand.logo_collapsed_url;
    if (v === null || v === undefined) {
        return null;
    }
    const s = String(v).trim();

    return s === '' ? null : s;
}

export function brandAuthCardBgRaw(brand: PageBrandLike | null | undefined): string | null {
    if (!brand) {
        return null;
    }
    const v = brand.authCardBgUrl ?? brand.auth_card_bg_url;
    if (v === null || v === undefined) {
        return null;
    }
    const s = String(v).trim();

    return s === '' ? null : s;
}

/**
 * Turn stored brand paths (e.g. brands/x.png) into browser-usable URLs.
 */
export function resolveBrandAssetUrl(url: string | null | undefined): string | null {
    if (url === null || url === undefined) {
        return null;
    }
    const u = String(url).trim();
    if (u === '') {
        return null;
    }
    if (/^https?:\/\//i.test(u)) {
        return u;
    }

    return `/storage/${u.replace(/^\//, '')}`;
}

/**
 * Logo/Auth-Medien: absolute URL, öffentlicher Pfad (/images/…), oder Storage-Pfad.
 */
export function resolveBrandMediaUrl(url: string | null | undefined): string | null {
    if (url === null || url === undefined) {
        return null;
    }
    const u = String(url).trim();
    if (u === '') {
        return null;
    }
    if (/^https?:\/\//i.test(u)) {
        return u;
    }
    if (u.startsWith('/')) {
        return u;
    }

    return `/storage/${u.replace(/^\//, '')}`;
}

const DEFAULT_AUTH_CARD_BG = '/images/auth-card-bg.svg';

export function useAuthCardBgUrl(): ComputedRef<string> {
    const page = usePage();

    return computed(() => {
        const raw = brandAuthCardBgRaw(page.props.brand as PageBrandLike | null);

        return resolveBrandMediaUrl(raw) ?? DEFAULT_AUTH_CARD_BG;
    });
}

export function useBrandLogos() {
    const page = usePage();
    const brand = computed(() => page.props.brand as PageBrandLike | null);

    const mainResolved = computed(() => resolveBrandAssetUrl(brandMainLogoRaw(brand.value)));

    const collapsedResolved = computed(() => {
        const collapsed = resolveBrandAssetUrl(brandCollapsedLogoRaw(brand.value));
        if (collapsed) {
            return collapsed;
        }

        return mainResolved.value;
    });

    return {
        /** Logo für dunkle Flächen (z. B. dunkle Sidebar) – Standard: logo.png */
        logoForDarkBg: computed(() => mainResolved.value ?? '/images/logo.png'),
        /** Logo für helle Flächen – Standard: logo-black.png */
        logoForLightBg: computed(() => mainResolved.value ?? '/images/logo-black.png'),
        /** Eingeklappte Sidebar: logo_collapsed_url, sonst Hauptlogo, sonst logo-sm */
        logoCollapsed: computed(() => collapsedResolved.value ?? '/images/logo-sm.png'),
    };
}
