/**
 * Shared display formatters for INR amounts, percentages, and dates.
 * Single source of truth — page components must not redefine these.
 */

export function formatCurrencyShort(value: number | string): string {
    const v = Number(value);
    const abs = Math.abs(v);
    const sign = v < 0 ? '−' : '';
    if (abs >= 10000000) return sign + '₹' + (abs / 10000000).toFixed(2) + ' Cr';
    if (abs >= 100000) return sign + '₹' + (abs / 100000).toFixed(2) + ' L';
    return sign + '₹' + abs.toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

export function formatCurrency(value: number | string): string {
    const v = Number(value);
    const sign = v < 0 ? '−' : '';
    return sign + '₹' + Math.abs(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** value is a fraction (0.1234 → "12.34%"); signed adds a leading + for positives. */
export function formatPercent(value: number, signed = false): string {
    const pct = (value * 100).toFixed(2) + '%';
    return signed && value >= 0 ? '+' + pct : pct;
}

/** "24 Mar 2024" — the standard full date everywhere on the page. */
export function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

/** "Mar '24" — unambiguous month-year for compact entry/exit columns. */
export function formatMonthYear(value: string): string {
    const d = new Date(value);
    const month = d.toLocaleDateString('en-IN', { month: 'short' });
    return month + " '" + String(d.getFullYear() % 100).padStart(2, '0');
}

export function formatHoldingPeriod(days: number): string {
    if (days < 1) return '<1d';
    if (days < 30) return `${days}d`;
    if (days < 365) return `${Math.max(1, Math.round(days / 30))}mo`;
    const years = Math.floor(days / 365);
    const months = Math.round((days % 365) / 30);
    return months === 0 ? `${years}y` : `${years}y ${months}mo`;
}

export function formatCompactNumber(value: number): string {
    if (value >= 10000000) return (value / 10000000).toFixed(0) + 'Cr';
    if (value >= 100000) return (value / 100000).toFixed(0) + 'L';
    if (value >= 1000) return (value / 1000).toFixed(0) + 'K';
    return String(value);
}
