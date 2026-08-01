/**
 * Display rules for screen result cells, keyed by column name. Covers every
 * ScreenResultColumnEnum and ScreenSortByOptionEnum value coming from the backend.
 *
 * Values arrive as strings (MySQL decimals) or server-formatted strings
 * (marketcap has thousands separators), so parsing always strips commas.
 */

type ScreenColumnKind = 'signedPercent' | 'percent' | 'price' | 'decimal' | 'integer' | 'formattedNumber' | 'text';

function screenColumnKind(column: string): ScreenColumnKind {
    if (/(^|_)return(_|$)/.test(column)) {
        return 'signedPercent';
    }

    if (column.startsWith('away_from_high') || column.startsWith('positive_days_percent')) {
        return 'percent';
    }

    if (column.startsWith('close_') || column.startsWith('ma_') || column.startsWith('high_')) {
        return 'price';
    }

    if (
        column.startsWith('rsi_') ||
        column.startsWith('average_rsi') ||
        column.startsWith('volatility_') ||
        column === 'beta' ||
        column === 'price_to_earnings' ||
        column === 'median_volume_one_year'
    ) {
        return 'decimal';
    }

    if (column.startsWith('circuits_')) {
        return 'integer';
    }

    if (column === 'marketcap') {
        return 'formattedNumber';
    }

    return 'text';
}

function isEmptyCellValue(value: unknown): boolean {
    return value === null || value === undefined || value === '' || value === '-';
}

function parseCellNumber(value: unknown): number {
    return Number(String(value).replace(/,/g, ''));
}

export function formatScreenCellValue(column: string, value: unknown): string {
    if (isEmptyCellValue(value)) {
        return '—';
    }

    const kind = screenColumnKind(column);

    if (kind === 'text' || kind === 'formattedNumber') {
        return String(value);
    }

    const numeric = parseCellNumber(value);

    if (!Number.isFinite(numeric)) {
        return String(value);
    }

    switch (kind) {
        case 'signedPercent':
            return numeric > 0 ? '+' + numeric.toFixed(2) : numeric.toFixed(2);
        case 'percent':
        case 'decimal':
            return numeric.toFixed(2);
        case 'price':
            return numeric.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        case 'integer':
            return String(Math.round(numeric));
    }
}

export function screenCellClass(column: string, value: unknown): string {
    if (isEmptyCellValue(value)) {
        return 'text-gray-400';
    }

    if (screenColumnKind(column) === 'signedPercent') {
        const numeric = parseCellNumber(value);

        if (numeric > 0) {
            return 'text-emerald-600';
        }

        if (numeric < 0) {
            return 'text-rose-600';
        }
    }

    return 'text-gray-500';
}

export function isTextScreenColumn(column: string): boolean {
    return screenColumnKind(column) === 'text';
}

export function screenColumnAlignClass(column: string): string {
    return isTextScreenColumn(column) ? 'text-left' : 'text-right';
}

/** Sort key for client-side re-sorting: numbers compare numerically, everything else as lowercased text, empty values as null. */
export function screenCellSortValue(value: unknown): number | string | null {
    if (isEmptyCellValue(value)) {
        return null;
    }

    const numeric = parseCellNumber(value);

    if (Number.isFinite(numeric)) {
        return numeric;
    }

    return String(value).toLowerCase();
}
