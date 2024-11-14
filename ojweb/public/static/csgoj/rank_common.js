function ValidateAwardRatio(ratio) {
    if(ratio < 100 && ratio > 1) {
        return ratio / 100.0;
    }
    return ratio;
}
function GetAwardRank(cnt_base, ratio_gold, ratio_silver, ratio_bronze) {
    ratio_gold = ValidateAwardRatio(ratio_gold);
    ratio_silver = ValidateAwardRatio(ratio_silver);
    ratio_bronze = ValidateAwardRatio(ratio_bronze);
    rank_gold = ratio_gold >= 100 ? ratio_gold - 100 : Math.ceil(cnt_base * ratio_gold);
    let tmp_ratio_gold = ratio_gold >= 100 ? rank_gold / cnt_base : ratio_gold;
    rank_silver = ratio_silver >= 100 ? rank_gold + ratio_silver - 100 : Math.ceil(cnt_base * (tmp_ratio_gold + ratio_silver));
    if(ratio_silver === 0) {
        rank_silver = rank_gold;
    }
    let tmp_ratio_silver = ratio_silver >= 100 ? rank_silver / cnt_base : tmp_ratio_gold + ratio_silver;
    rank_bronze = ratio_bronze >= 100 ? rank_silver + ratio_bronze - 100 : Math.ceil(cnt_base * (tmp_ratio_silver + ratio_bronze));
    if(ratio_bronze === 0) {
        rank_bronze = rank_silver;
    }
    return [rank_gold, rank_silver, rank_bronze];
}