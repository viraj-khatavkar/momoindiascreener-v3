export interface MarketHeartbeat {
    id: number;
    date: string;
    index: string;
    percentage_above_ma_200: number;
    percentage_above_ma_100: number;
    percentage_above_ma_50: number;
    percentage_above_ma_20: number;
    percentage_of_stocks_with_returns_one_year_above_zero: number;
    percentage_of_stocks_with_returns_one_year_above_ten: number;
    percentage_of_stocks_with_returns_one_year_above_hundred: number;
    percentage_of_stocks_within_ten_percent_of_ath: number;
    percentage_of_stocks_within_twenty_percent_of_ath: number;
    percentage_of_stocks_within_thirty_percent_of_ath: number;
    advances: number;
    declines: number;
    advance_decline_ratio: number;
    created_at: string;
    updated_at: string;
}
