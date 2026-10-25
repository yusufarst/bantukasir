// Joins class names, leaving out the empty ones (the cn() of shadcn/ui,
// without tailwind-merge: the components' classes are the design system's
// own and are never overridden by a conflicting utility).
export type ClassValue = string | false | null | undefined;

export function cn(...classes: readonly ClassValue[]): string {
    return classes.filter((value): value is string => typeof value === 'string' && value !== '').join(' ');
}
