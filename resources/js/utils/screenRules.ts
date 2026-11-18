import type { Screen } from '@/types/app/Models/Screen';
import type { SelectOption } from '@/types/SelectOption';
import { screenSortByDisplayName } from '@/utils/screenSortByDisplayName';

export interface ScreenRuleGroup {
    title: string;
    rules: { label: string; value: string }[];
}

export function screenOptionLabel(options: SelectOption[], value: string): string {
    return options.find((option) => option.id === value)?.name ?? screenSortByDisplayName(value);
}

export function screenRuleGroups(screen: Screen, filterScopes: SelectOption[], customValues: SelectOption[]): ScreenRuleGroup[] {
    const number = (value: number) => Number(value).toLocaleString('en-IN', { maximumFractionDigits: 6 });
    const direction = (value: string) => (value === 'asc' ? 'Lowest to Highest' : 'Highest to Lowest');
    const factor = (key: string, order: string) => `${screenSortByDisplayName(key)} · ${direction(order)}`;
    const periods = [
        ['one_year', 'One year'],
        ['nine_months', 'Nine months'],
        ['six_months', 'Six months'],
        ['three_months', 'Three months'],
        ['one_months', 'One month'],
    ] as const;
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

    return [
        {
            title: 'Universe and ranking',
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
        {
            title: 'General Filters',
            rules: [
                { label: 'Apply filters on', value: screenOptionLabel(filterScopes, screen.apply_filters_on) },
                {
                    label: 'One-year return',
                    value: Number(screen.minimum_return_one_year) > -100 ? `More than ${number(screen.minimum_return_one_year)}%` : 'No minimum',
                },
                { label: 'Median daily volume over one year', value: `At least ₹${number(screen.median_volume_one_year)}` },
            ],
        },
        {
            title: 'Moving Average Filters',
            rules: [
                { label: 'Moving averages (adjusted close)', value: screen.apply_ma && movingAverages.length ? movingAverages.join(', ') : 'Off' },
            ],
        },
        {
            title: 'Exponential Moving Average Filters',
            rules: [
                {
                    label: 'Exponential moving averages (adjusted close)',
                    value: screen.apply_ema && exponentialMovingAverages.length ? exponentialMovingAverages.join(', ') : 'Off',
                },
            ],
        },
        {
            title: 'Away from High Filters',
            rules: [
                { label: 'Distance below one-year high', value: `Less than ${number(screen.away_from_high_one_year)}%` },
                { label: 'Distance below all-time high', value: `Less than ${number(screen.away_from_high_all_time)}%` },
            ],
        },
        {
            title: 'Percentage of Positive Days Filters',
            rules: periods.map(([key, label]) => ({ label, value: `At least ${number(screen[`positive_days_percent_${key}`])}%` })),
        },
        { title: 'Circuit Filters', rules: periods.map(([key, label]) => ({ label, value: `At most ${number(screen[`circuits_${key}`])}` })) },
        {
            title: 'Marketcap Range (in crores)',
            rules: [{ label: 'Market cap (₹ crore)', value: `${number(screen.marketcap_from)} to ${number(screen.marketcap_to)} (inclusive)` }],
        },
        {
            title: 'Price to Earnings Range',
            rules: [
                {
                    label: 'P/E',
                    value: screen.apply_pe ? `${number(screen.price_to_earnings_from)} to ${number(screen.price_to_earnings_to)} (inclusive)` : 'Off',
                },
            ],
        },
        {
            title: 'Series',
            rules: [
                {
                    label: 'Series',
                    value: [screen.series_eq && 'EQ', screen.series_be && 'BE'].filter(Boolean).join(', ') || 'None selected — no stocks qualify',
                },
            ],
        },
        {
            title: 'Ignore Above Beta',
            rules: [{ label: 'Maximum beta', value: Number(screen.ignore_above_beta) < 100 ? number(screen.ignore_above_beta) : 'No limit' }],
        },
        {
            title: 'Price (CMP) Range',
            rules: [{ label: 'Closing price (raw)', value: `₹${number(screen.price_from)} to ₹${number(screen.price_to)} (inclusive)` }],
        },
        {
            title: 'Multi-Factor Combined Ranking',
            rules: [
                { label: 'Factor two', value: screen.apply_factor_two ? factor(screen.factor_two_sort_by, screen.factor_two_sort_direction) : 'Off' },
                {
                    label: 'Factor three',
                    value: screen.apply_factor_three ? factor(screen.factor_three_sort_by, screen.factor_three_sort_direction) : 'Off',
                },
            ],
        },
        {
            title: 'Historical Ranks',
            rules: [{ label: 'Requested date', value: screen.apply_historical_date ? screen.historical_date : 'Latest available date' }],
        },
        { title: 'Custom Filters', rules: customRules.length ? customRules : [{ label: 'Custom filters', value: 'Off' }] },
    ];
}
