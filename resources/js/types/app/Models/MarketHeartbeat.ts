export interface MarketHeartbeat {
    id: number;
    date: string;
    index: string;
    percentage_above_ma_200: number | null;
    percentage_above_ma_100: number | null;
    percentage_above_ma_50: number | null;
    percentage_above_ma_20: number | null;
    percentage_of_stocks_with_returns_one_year_above_zero: number | null;
    percentage_of_stocks_with_returns_one_year_above_ten: number | null;
    percentage_of_stocks_with_returns_one_year_above_hundred: number | null;
    percentage_of_stocks_within_ten_percent_of_ath: number | null;
    percentage_of_stocks_within_twenty_percent_of_ath: number | null;
    percentage_of_stocks_within_thirty_percent_of_ath: number | null;
    advances: number;
    declines: number;
    advance_decline_ratio: number;
    created_at: string;
    updated_at: string;
}
