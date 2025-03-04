CREATE TABLE IF NOT EXISTS pending (
  `id` integer primary key,
  `date` datetime NOT NULL,
  `gateway` varchar(255) NOT NULL,
  `gateway_account` varchar(255) NULL,
  `order_id` varchar(255) NULL,
  `gateway_txn_id` varchar(255) NULL,
  `payment_method` varchar(16) DEFAULT NULL,
  `message` text NOT NULL
);
