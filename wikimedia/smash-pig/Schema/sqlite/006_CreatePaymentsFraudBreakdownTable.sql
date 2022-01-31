CREATE TABLE IF NOT EXISTS `payments_fraud_breakdown` (
  `id` integer PRIMARY KEY,
  `payments_fraud_id` integer NULL,
  `filter_name` varchar(64) NULL,
  `risk_score` float NULL
);
