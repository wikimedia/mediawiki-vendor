CREATE TABLE IF NOT EXISTS payments_initial (
  `id` integer primary key,
  `contribution_tracking_id` integer NULL,
  `gateway` varchar(255) NULL,
  `order_id` varchar(255) NULL,
  `gateway_txn_id` varchar(255) NULL,
  `validation_action` varchar(16) NULL,
  `payments_final_status` varchar(16) NULL,
  `payment_method` varchar(16) NULL,
  `payment_submethod` varchar(32) NULL,
  `country` varchar(2) NULL,
  `amount` float NULL,
  `currency_code` varchar(3) NULL,
  `server` varchar(64) NULL,
  `date` datetime NULL
);
