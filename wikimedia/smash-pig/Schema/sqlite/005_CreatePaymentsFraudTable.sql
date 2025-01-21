CREATE TABLE IF NOT EXISTS payments_fraud (
  `id` integer primary key,
  `contribution_tracking_id` integer NULL,
  `gateway` varchar(255) NULL,
  `order_id` varchar(255) NULL,
  `validation_action` varchar(16) NULL,
  `user_ip` varchar(16),
  `payment_method` varchar(16) NULL,
  `risk_score` float NULL,
  `server` varchar(64) NULL,
  `date` datetime NULL
);
