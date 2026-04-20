ALTER TABLE `users`
  ADD COLUMN `preferred_currency` ENUM('usd','eur','rub','uah','byn','ils') NOT NULL DEFAULT 'usd' AFTER `balance`;
