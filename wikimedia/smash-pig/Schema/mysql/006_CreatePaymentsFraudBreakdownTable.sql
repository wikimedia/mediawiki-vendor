CREATE TABLE IF NOT EXISTS `payments_fraud_breakdown` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payments_fraud_id` bigint(20) unsigned DEFAULT NULL,
  `filter_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `risk_score` float DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_fraud_id` (`payments_fraud_id`),
  KEY `filter_name` (`filter_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks breakdown of donation fraud scores for all donations.';
