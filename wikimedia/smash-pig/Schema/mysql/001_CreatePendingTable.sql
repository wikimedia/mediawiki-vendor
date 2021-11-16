CREATE TABLE IF NOT EXISTS pending (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL,
  `gateway` varchar(255) NOT NULL,
  `gateway_account` varchar(255) NULL,
  `order_id` varchar(255) NULL,
  `gateway_txn_id` varchar(255) NULL,
  `message` text NOT NULL,
  INDEX `idx_pending_date` (`date`),
  INDEX `idx_pending_date_gateway` (`date`, `gateway`),
  INDEX `idx_pending_order_id_gateway` (`order_id`, `gateway`),
  INDEX `idx_pending_gateway_txn_id_gateway` (`gateway_txn_id`, `gateway`),
  PRIMARY KEY `pk_pending_id` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
