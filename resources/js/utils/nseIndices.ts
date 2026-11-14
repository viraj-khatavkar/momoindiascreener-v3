export type IndexValue = number | string | null | undefined;

export function indexNumber(value: IndexValue): number | null {
    if (value === null || value === undefined || value === '') return null;
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}

export function formatIndexNumber(value: IndexValue, decimals = 2): string {
    return indexNumber(value)?.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) ?? '—';
}

export function formatIndexChange(value: IndexValue): string {
    const number = indexNumber(value);
    return number === null ? '—' : `${number > 0 ? '+' : number < 0 ? '−' : ''}${formatIndexNumber(Math.abs(number))}`;
}

export function indexChangeClass(value: IndexValue): string {
    const number = indexNumber(value);
    return number === null || number === 0 ? 'text-[#706b7d]' : number > 0 ? 'text-[#24745e]' : 'text-[#b44854]';
}

export function formatIndexDate(date: string): string {
    return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(
        new Date(`${date.slice(0, 10)}T00:00:00Z`),
    );
}

export const indexPeriods = [
    { key: '1M', months: 1, label: '1 month' },
    { key: '3M', months: 3, label: '3 months' },
    { key: '6M', months: 6, label: '6 months' },
    { key: '1Y', months: 12, label: '1 year' },
    { key: '3Y', months: 36, label: '3 years' },
    { key: '5Y', months: 60, label: '5 years' },
    { key: '10Y', months: 120, label: '10 years' },
    { key: 'Max', months: null, label: 'all available history' },
] as const;
export type IndexPeriod = (typeof indexPeriods)[number]['key'];

export function indexPeriodStart(end: string, period: IndexPeriod): string | null {
    const months = indexPeriods.find((item) => item.key === period)?.months;
    if (months == null) return null;
    const start = new Date(`${end.slice(0, 10)}T00:00:00Z`);
    const day = start.getUTCDate();
    start.setUTCDate(1);
    start.setUTCMonth(start.getUTCMonth() - months);
    const monthEnd = new Date(Date.UTC(start.getUTCFullYear(), start.getUTCMonth() + 1, 0)).getUTCDate();
    start.setUTCDate(Math.min(day, monthEnd));
    return start.toISOString().slice(0, 10);
}
