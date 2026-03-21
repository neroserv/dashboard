export type UseInitialsReturn = {
    getInitials: (fullName?: string) => string;
};

/**
 * First letter of each word (e.g. "Alessio Meier" → "AM", "Sven Oliver Zimmer" → "SOZ").
 */
export function getInitials(fullName?: string): string {
    if (!fullName?.trim()) {
        return '?';
    }

    const parts = fullName
        .trim()
        .split(/\s+/)
        .map((p) => p.trim())
        .filter((p) => p.length > 0);

    if (parts.length === 0) {
        return '?';
    }

    return parts.map((p) => p.charAt(0).toUpperCase()).join('');
}

export function useInitials(): UseInitialsReturn {
    return { getInitials };
}
