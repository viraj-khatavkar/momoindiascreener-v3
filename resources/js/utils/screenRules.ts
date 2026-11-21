import type { ScreenSettings } from '@/types/ScreenSettings';
import type { SelectOption } from '@/types/SelectOption';
import { screenFactorLabel, screenSortByDisplayName } from '@/utils/screenSortByDisplayName';

export interface ScreenRuleGroup {
    title: string;
    summary: string;
    rules: { label: string; value: string }[];
}

export function screenOptionLabel(options: SelectOption[], value: string): string {
    return options.find((option) => option.id === value)?.name ?? screenSortByDisplayName(value);
}

export const screenPeriods = [
    ['one_year', 'One year'],
    ['nine_months', 'Nine months'],
    ['six_months', 'Six months'],
    ['three_months', 'Three months'],
    ['one_months', 'One month'],
] as const;

export const unlimitedCircuits = 300;

export function screenRuleGroups(screen: ScreenSettings, filterScopes: SelectOption[], customValues: SelectOption[]) {
    const number = (value: number) =>
        value == null || String(value).trim() === '' ? '—' : Number(value).toLocaleString('en-IN', { maximumFractionDigits: 6 });
    const direction = (value: string) => (value === 'asc' ? 'Lowest to Highest' : 'Highest to Lowest');
    const factor = (key: string, order: string) => `${screenFactorLabel(key)} · ${direction(order)}`;
    const movingAverages = ([200, 100, 50, 20] as const).flatMap((days) =>
        (['above', 'below'] as const)
            .filter((side) => screen[`${side}_ma_${days}`])
            .map((side) => `${side === 'above' ? 'Above' : 'Below'} ${days}-day MA`),
    );
    const exponentialMovingAverages = ([200, 100, 50, 20] as const).flatMap((days) =>
        (['above', 'below'] as const)
            .filter((side) => screen[`${side}_ema_${days}`])
            .map((side) => `${side === 'above' ? 'Above' : 'Below'} ${days}-day EMA`),
    );
    const customRules = (['one', 'two', 'three', 'four', 'five'] as const)
        .filter((slot) => screen[`apply_custom_filter_${slot}`])
        .map((slot) => ({
            label: `Custom filter ${slot}`,
            value: `${screenOptionLabel(customValues, screen[`custom_filter_${slot}_value_one`])} ${screen[`custom_filter_${slot}_operator`]} ${screenOptionLabel(customValues, screen[`custom_filter_${slot}_value_two`])}`,
        }));

    const stockValuations = screen.index !== 'etf';
    const ma = screen.apply_ma && movingAverages.length ? movingAverages.join(', ') : 'Off';
    const ema = screen.apply_ema && exponentialMovingAverages.length ? exponentialMovingAverages.join(', ') : 'Off';
    const marketcap = stockValuations ? `₹${number(screen.marketcap_from)} to ₹${number(screen.marketcap_to)} crore` : 'Unavailable for ETFs';
    const pe = stockValuations
        ? screen.apply_pe
            ? `${number(screen.price_to_earnings_from)} to ${number(screen.price_to_earnings_to)}`
            : 'Off'
        : 'Unavailable for ETFs';
    const series = [screen.series_eq && 'EQ', screen.series_be && 'BE'].filter(Boolean).join(', ');
    const beta = Number(screen.ignore_above_beta) < 100 ? number(screen.ignore_above_beta) : 'No limit';
    const price = `₹${number(screen.price_from)} to ₹${number(screen.price_to)}`;
    const date = screen.apply_historical_date ? screen.historical_date : 'Latest available date';
    const scope = screenOptionLabel(filterScopes, screen.apply_filters_on);
    const positiveDays = screenPeriods.filter(([key]) => Number(screen[`positive_days_percent_${key}`]) > 0);
    const circuitLimits = screenPeriods.filter(([key]) => Number(screen[`circuits_${key}`]) !== unlimitedCircuits);

    return {
        universe: {
            title: 'Universe and ranking',
            summary: screenFactorLabel(screen.sort_by),
            rules: [
                { label: 'Universe', value: screenSortByDisplayName(screen.index) },
                { label: 'Factor one', value: factor(screen.sort_by, screen.sort_direction) },
                ...(screen.index === 'etf'
                    ? [
                          {
                              label: 'One ETF per underlying index',
                              value: screen.unique_etf_per_index ? 'On · highest median daily volume over one year after filters' : 'Off',
                          },
                      ]
                    : []),
            ],
        },
        general: {
            title: 'General Filters',
            summary: [
                scope,
                Number(screen.minimum_return_one_year) > -100 && `Return > ${number(screen.minimum_return_one_year)}%`,
                Number(screen.median_volume_one_year) > 0 && `Volume ≥ ₹${number(screen.median_volume_one_year)}`,
            ]
                .filter(Boolean)
                .join(' · '),
            rules: [
                { label: 'Apply filters on', value: scope },
                {
                    label: 'One-year return',
                    value: Number(screen.minimum_return_one_year) > -100 ? `More than ${number(screen.minimum_return_one_year)}%` : 'No minimum',
                },
                { label: 'Median daily volume over one year', value: `At least ₹${number(screen.median_volume_one_year)}` },
            ],
        },
        ma: {
            title: 'Moving Average Filters',
            summary: ma,
            rules: [{ label: 'Moving averages (adjusted close)', value: ma }],
        },
        ema: {
            title: 'Exponential Moving Average Filters',
            summary: ema,
            rules: [{ label: 'Exponential moving averages (adjusted close)', value: ema }],
        },
        highs: {
            title: 'Away from High Filters',
            summary:
                [
                    Number(screen.away_from_high_all_time) < 100 && `Less than ${number(screen.away_from_high_all_time)}% below the all-time high`,
                    Number(screen.away_from_high_one_year) < 100 && `Less than ${number(screen.away_from_high_one_year)}% below the one-year high`,
                ]
                    .filter(Boolean)
                    .join(' · ') || 'No limit',
            rules: [
                { label: 'Distance below one-year high', value: `Less than ${number(screen.away_from_high_one_year)}%` },
                { label: 'Distance below all-time high', value: `Less than ${number(screen.away_from_high_all_time)}%` },
            ],
        },
        positiveDays: {
            title: 'Percentage of Positive Days Filters',
            summary: positiveDays.map(([key, label]) => `${label} ≥ ${number(screen[`positive_days_percent_${key}`])}%`).join(' · ') || 'No minimum',
            rules: screenPeriods.map(([key, label]) => ({ label, value: `At least ${number(screen[`positive_days_percent_${key}`])}%` })),
        },
        circuits: {
            title: 'Circuit Filters',
            summary: circuitLimits.map(([key, label]) => `${label} ≤ ${number(screen[`circuits_${key}`])}`).join(' · ') || 'No limit',
            rules: screenPeriods.map(([key, label]) => ({
                label,
                value: Number(screen[`circuits_${key}`]) === unlimitedCircuits ? 'No limit' : `At most ${number(screen[`circuits_${key}`])}`,
            })),
        },
        marketcap: {
            title: 'Market Cap Range (₹ crore)',
            summary: marketcap,
            rules: [
                {
                    label: 'Market cap (₹ crore)',
                    value: stockValuations ? `${number(screen.marketcap_from)} to ${number(screen.marketcap_to)} (inclusive)` : marketcap,
                },
            ],
        },
        pe: {
            title: 'Price-to-Earnings (P/E) Range',
            summary: pe,
            rules: [{ label: 'P/E', value: stockValuations && screen.apply_pe ? `${pe} (inclusive)` : pe }],
        },
        series: {
            title: 'Series',
            summary: series || 'None selected',
            rules: [{ label: 'Series', value: series || 'None selected — no stocks qualify' }],
        },
        beta: {
            title: 'Ignore Above Beta',
            summary: beta,
            rules: [{ label: 'Maximum beta', value: beta }],
        },
        price: {
            title: 'Price (CMP) Range',
            summary: price,
            rules: [{ label: 'Closing price (raw)', value: `${price} (inclusive)` }],
        },
        multiFactor: {
            title: 'Multi-Factor Combined Ranking',
            summary:
                [
                    screen.apply_factor_two && factor(screen.factor_two_sort_by, screen.factor_two_sort_direction),
                    screen.apply_factor_three && factor(screen.factor_three_sort_by, screen.factor_three_sort_direction),
                ]
                    .filter(Boolean)
                    .join(' + ') || 'Off',
            rules: [
                { label: 'Factor two', value: screen.apply_factor_two ? factor(screen.factor_two_sort_by, screen.factor_two_sort_direction) : 'Off' },
                {
                    label: 'Factor three',
                    value: screen.apply_factor_three ? factor(screen.factor_three_sort_by, screen.factor_three_sort_direction) : 'Off',
                },
            ],
        },
        historical: {
            title: 'Historical Ranks',
            summary: date,
            rules: [{ label: 'Requested date', value: date }],
        },
        custom: {
            title: 'Custom Filters',
            summary: customRules.map((rule) => rule.value).join(' · ') || 'Off',
            rules: customRules.length ? customRules : [{ label: 'Custom filters', value: 'Off' }],
        },
    } satisfies Record<string, ScreenRuleGroup>;
}
