-- CirculAPU realistic append-only dataset
-- Existing rows are preserved. INSERT IGNORE prevents duplicate-key updates.
SET NAMES utf8mb4;
SET max_recursive_iterations = 5000;
START TRANSACTION;

-- 400 users: U007-U406 (360 Members, 20 Managers, 20 Auditors)
INSERT IGNORE INTO `user`
(`userID`,`name`,`email`,`password`,`role`,`status`,`ecopoint`,`dateCreated`)
WITH RECURSIVE seq AS (
  SELECT 1 n UNION ALL SELECT n + 1 FROM seq WHERE n < 400
)
SELECT
  CONCAT('U', LPAD(n + 6, 3, '0')),
  CONCAT(
    ELT(1 + MOD(n - 1, 20), 'Aiman','Aisha','Amir','Anis','Daniel','Devi','Farah','Hafiz','Hannah','Irfan','Jia Wei','Kavitha','Lee','Mei Ling','Nadia','Nicholas','Nurul','Pravin','Sara','Wei Jian'),
    ' ',
    ELT(1 + MOD(FLOOR((n - 1) / 20), 12), 'Abdullah','Chan','Goh','Ismail','Kaur','Kumar','Lee','Lim','Ng','Ong','Rahman','Tan')
  ),
  CONCAT('tp', LPAD(60000 + n, 6, '0'), '@mail.apu.edu.my'),
  '$2y$10$MaF3JclH8iDXCv7tbzETsOD3t6a9AQ9S4CzwA5WW/bFoaayRZqBYK',
  CASE WHEN n <= 360 THEN 'Member' WHEN n <= 380 THEN 'Manager' ELSE 'Auditor' END,
  CASE WHEN MOD(n, 20) = 0 THEN 'Suspended' ELSE 'Active' END,
  MOD(n * 137, 2501),
  TIMESTAMP('2026-08-17 12:00:00') - INTERVAL MOD(n * 37, 365) DAY
FROM seq;

-- 1,200 assets: A009-A1208
INSERT IGNORE INTO `assets`
(`assetID`,`ownerID`,`categoryID`,`assetName`,`description`,`assetCondition`,`imageURL`,`availabilityStatus`,`listingStatus`,`createdDate`,`verificationStatus`)
WITH RECURSIVE seq AS (
  SELECT 1 n UNION ALL SELECT n + 1 FROM seq WHERE n < 1200
)
SELECT
  CONCAT('A', IF(n + 8 < 1000, LPAD(n + 8, 3, '0'), n + 8)),
  CONCAT('U', LPAD(7 + MOD(n * 7, 360), 3, '0')),
  CONCAT('C', LPAD(1 + MOD(n - 1, 4), 3, '0')),
  ELT(1 + MOD(n - 1, 16), 'Logitech Wireless Mouse','Java Programming Textbook','USB-C Fast Charger','Portable HDMI Adapter','Wireless Keyboard','Data Structures Textbook','Stainless Steel Water Bottle','Adjustable Laptop Stand','Scientific Calculator','LED Desk Lamp','Badminton Racket','Canvas Backpack','Bluetooth Headphones','Document Storage Box','Ergonomic Office Chair','Portable Power Bank'),
  ELT(1 + MOD(n - 1, 8), 'Well maintained and ready for another owner.','Suitable for classes and everyday campus use.','Fully functional with minor signs of normal use.','Kept in good condition and no longer needed.','Available to support another student or staff member.','Clean, tested and ready for collection on campus.','A practical item for study, work or accommodation.','Shared to encourage reuse within the APU community.'),
  ELT(1 + MOD(n - 1, 5), 'New','Like New','Good','Fair','Poor'),
  NULL,
  CASE
    WHEN MOD(n,10) IN (0,1,2,8,9) THEN 'Unavailable'
    ELSE ELT(1 + MOD(n,3), 'Available','Reserved','Borrowed')
  END,
  CASE
    WHEN MOD(n,10)=0 THEN 'Draft'
    WHEN MOD(n,10)=1 THEN 'Pending Review'
    WHEN MOD(n,10)=2 THEN 'Ready for Verification'
    WHEN MOD(n,10)=8 THEN 'Rejected'
    WHEN MOD(n,10)=9 THEN 'Removed'
    ELSE 'Published'
  END,
  TIMESTAMP('2026-08-17 12:00:00') - INTERVAL MOD(n * 31, 365) DAY,
  CASE WHEN MOD(n,10) IN (0,1,2) THEN 'Pending' WHEN MOD(n,10) IN (8,9) THEN 'Rejected' ELSE 'Approved' END
FROM seq;

-- 1,000 verification records: V008-V1007
INSERT IGNORE INTO `verificationrecords`
(`verificationID`,`userID`,`verifiedBy`,`targetID`,`targetType`,`status`,`remarks`,`verifiedDate`)
WITH RECURSIVE seq AS (
  SELECT 1 n UNION ALL SELECT n + 1 FROM seq WHERE n < 1000
)
SELECT
  CONCAT('V', IF(n + 7 < 1000, LPAD(n + 7, 3, '0'), n + 7)),
  CONCAT('U', LPAD(7 + MOD(n * 7, 360), 3, '0')),
  CASE WHEN n <= 350 OR MOD(n,3)=0 THEN NULL ELSE CONCAT('U', LPAD(387 + MOD(n,20), 3, '0')) END,
  CONCAT('A', IF(n + 8 < 1000, LPAD(n + 8, 3, '0'), n + 8)),
  'Asset',
  CASE WHEN n <= 350 OR MOD(n,3)=0 THEN 'Pending' WHEN MOD(n,3)=1 THEN 'Approved' ELSE 'Rejected' END,
  CASE WHEN n <= 350 OR MOD(n,3)=0 THEN 'Awaiting sustainability review.' WHEN MOD(n,3)=1 THEN 'Asset details and condition were verified.' ELSE 'The submission did not meet verification requirements.' END,
  CASE WHEN n <= 350 OR MOD(n,3)=0 THEN NULL ELSE TIMESTAMP('2026-08-17 12:00:00') - INTERVAL MOD(n * 19, 240) DAY END
FROM seq;

-- 350 escalation reports: R001-R350
INSERT IGNORE INTO `report`
(`reportID`,`reporterID`,`verificationID`,`reason`,`evidenceImage`,`status`,`timeCreated`)
WITH RECURSIVE seq AS (
  SELECT 1 n UNION ALL SELECT n + 1 FROM seq WHERE n < 350
)
SELECT
  CONCAT('R', LPAD(n, 3, '0')),
  CONCAT('U', LPAD(7 + MOD(n * 11, 360), 3, '0')),
  CONCAT('V', IF(n + 7 < 1000, LPAD(n + 7, 3, '0'), n + 7)),
  ELT(1 + MOD(n - 1, 6), 'The verification decision appears to be incorrect.','The listed condition does not match the actual asset.','Important ownership information may be missing.','The listing may duplicate another existing asset.','The evidence provided requires another review.','The asset description may be misleading or incomplete.'),
  NULL,
  CASE WHEN MOD(n,4)=0 THEN 'Resolved' ELSE 'Pending' END,
  TIMESTAMP('2026-08-17 12:00:00') - INTERVAL MOD(n * 13, 180) DAY
FROM seq;

-- 1,800 transaction requests: TR003-TR1802
INSERT IGNORE INTO `transactionrequests`
(`transactionID`,`assetID`,`requesterID`,`requestDate`,`requestStatus`,`managerRemark`,`decisionDate`,`requestMessage`)
WITH RECURSIVE seq AS (
  SELECT 1 n UNION ALL SELECT n + 1 FROM seq WHERE n < 1800
)
SELECT
  CONCAT('TR', IF(n + 2 < 1000, LPAD(n + 2, 3, '0'), n + 2)),
  CONCAT('A', IF(8 + (FLOOR(MOD(n-1,600)/5)*10 + 3 + MOD(n-1,5)) < 1000, LPAD(8 + (FLOOR(MOD(n-1,600)/5)*10 + 3 + MOD(n-1,5)), 3, '0'), 8 + (FLOOR(MOD(n-1,600)/5)*10 + 3 + MOD(n-1,5)))),
  CONCAT('U', LPAD(7 + MOD(n * 17 + 3, 360), 3, '0')),
  TIMESTAMP('2026-08-17 12:00:00') - INTERVAL MOD(n * 17, 300) DAY,
  ELT(1 + MOD(n - 1, 7), 'Pending','Approved','In Progress','Completed','Cancelled','Rejected','Overdue'),
  CASE WHEN MOD(n-1,7)=0 THEN NULL ELSE ELT(1 + MOD(n-1,7), NULL,'Request approved after review.','Collection arrangements confirmed.','Transaction completed successfully.','Request cancelled by the requester.','Request could not be approved.','Return date has passed.') END,
  CASE WHEN MOD(n-1,7)=0 THEN NULL ELSE TIMESTAMP('2026-08-17 12:00:00') - INTERVAL MOD(n * 17 - 2, 300) DAY END,
  ELT(1 + MOD(n-1,5), 'I would like to borrow this item for coursework.','This item would be useful for an upcoming campus activity.','May I collect this item during the weekday?','I need this item temporarily for a student project.','Please let me know the available collection time.')
FROM seq;

-- 1,000 borrowings: B002-B1001
INSERT IGNORE INTO `borrowings`
(`borrowID`,`assetID`,`borrowerID`,`transactionID`,`borrowDate`,`expectedReturnDate`,`actualReturnDate`,`borrowingStatus`)
WITH RECURSIVE seq AS (
  SELECT 1 n UNION ALL SELECT n + 1 FROM seq WHERE n < 1000
)
SELECT
  CONCAT('B', IF(n + 1 < 1000, LPAD(n + 1, 3, '0'), n + 1)),
  CONCAT('A', IF(8 + (FLOOR(MOD(n-1,600)/5)*10 + 3 + MOD(n-1,5)) < 1000, LPAD(8 + (FLOOR(MOD(n-1,600)/5)*10 + 3 + MOD(n-1,5)), 3, '0'), 8 + (FLOOR(MOD(n-1,600)/5)*10 + 3 + MOD(n-1,5)))),
  CONCAT('U', LPAD(7 + MOD(n * 17 + 3, 360), 3, '0')),
  CONCAT('TR', IF(n + 2 < 1000, LPAD(n + 2, 3, '0'), n + 2)),
  TIMESTAMP('2026-08-17 12:00:00') - INTERVAL MOD(n * 11, 250) DAY,
  TIMESTAMP('2026-08-17 12:00:00') - INTERVAL MOD(n * 11, 250) DAY + INTERVAL (14 + MOD(n,15)) DAY,
  CASE WHEN MOD(n-1,6) IN (0,3) THEN TIMESTAMP('2026-08-17 12:00:00') - INTERVAL MOD(n * 11, 250) DAY + INTERVAL (5 + MOD(n,20)) DAY ELSE NULL END,
  ELT(1 + MOD(n-1,6), 'Completed','Borrowed','Overdue','Returned','Cancelled','Approved')
FROM seq;

-- 2,500 EcoPoint transactions: ET004-ET2503
INSERT IGNORE INTO `ecopointstransaction`
(`transactionID`,`userID`,`points`,`transactionType`,`sourceType`,`referenceID`,`transactionDate`)
WITH RECURSIVE seq AS (
  SELECT 1 n UNION ALL SELECT n + 1 FROM seq WHERE n < 2500
)
SELECT
  CONCAT('ET', IF(n + 3 < 1000, LPAD(n + 3, 3, '0'), n + 3)),
  CONCAT('U', LPAD(7 + MOD(n * 19, 360), 3, '0')),
  CASE WHEN MOD(n,5)=0 THEN -ELT(1 + MOD(n,4),10,20,30,50) ELSE ELT(1 + MOD(n,6),10,20,25,50,75,100) END,
  CASE WHEN MOD(n,5)=0 THEN 'Deducted' ELSE 'Earned' END,
  CASE WHEN MOD(n,5)=0 THEN 'RewardRedemption' ELSE 'Borrowing' END,
  CASE WHEN n <= 1000 THEN CONCAT('B', IF(n + 1 < 1000, LPAD(n + 1, 3, '0'), n + 1)) ELSE NULL END,
  TIMESTAMP('2026-08-17 12:00:00') - INTERVAL MOD(n * 23, 365) DAY
FROM seq;

-- 1,800 notifications: N008-N1807
INSERT IGNORE INTO `notification`
(`notificationID`,`userID`,`title`,`content`,`status`,`createdDate`)
WITH RECURSIVE seq AS (
  SELECT 1 n UNION ALL SELECT n + 1 FROM seq WHERE n < 1800
)
SELECT
  CONCAT('N', IF(n + 7 < 1000, LPAD(n + 7, 3, '0'), n + 7)),
  CONCAT('U', LPAD(7 + MOD(n * 23, 400), 3, '0')),
  ELT(1 + MOD(n-1,4), 'Request Status Updated','Verification Completed','EcoPoints Awarded','Upcoming Return Reminder'),
  ELT(1 + MOD(n-1,4), 'There is a new update on your borrowing request.','A sustainability auditor has completed a verification review.','EcoPoints have been added to your account.','An asset return date is approaching. Please review your borrowing details.'),
  CASE WHEN MOD(n,3)=0 THEN 'Read' ELSE 'Unread' END,
  TIMESTAMP('2026-08-17 12:00:00') - INTERVAL MOD(n * 7, 120) DAY
FROM seq;

-- 3,000 audit logs: LOG027-LOG3026
INSERT IGNORE INTO `systemlog`
(`logID`,`userID`,`actionType`,`refType`,`refID`,`description`,`timeLogged`)
WITH RECURSIVE seq AS (
  SELECT 1 n UNION ALL SELECT n + 1 FROM seq WHERE n < 3000
)
SELECT
  CONCAT('LOG', IF(n + 26 < 1000, LPAD(n + 26, 3, '0'), n + 26)),
  CONCAT('U', LPAD(7 + MOD(n * 29, 400), 3, '0')),
  ELT(1 + MOD(n-1,8), 'Create','Submit','Approve','Reject','Update','Assign','Login','Delete'),
  ELT(1 + MOD(n-1,6), 'User','Asset','Verification','Transaction','Borrowing','Category'),
  CASE MOD(n-1,6)
    WHEN 0 THEN CONCAT('U', LPAD(7 + MOD(n,360), 3, '0'))
    WHEN 1 THEN CONCAT('A', IF(9 + MOD(n-1,1200) < 1000, LPAD(9 + MOD(n-1,1200), 3, '0'), 9 + MOD(n-1,1200)))
    WHEN 2 THEN CONCAT('V', IF(8 + MOD(n-1,1000) < 1000, LPAD(8 + MOD(n-1,1000), 3, '0'), 8 + MOD(n-1,1000)))
    WHEN 3 THEN CONCAT('TR', IF(3 + MOD(n-1,1800) < 1000, LPAD(3 + MOD(n-1,1800), 3, '0'), 3 + MOD(n-1,1800)))
    WHEN 4 THEN CONCAT('B', IF(2 + MOD(n-1,1000) < 1000, LPAD(2 + MOD(n-1,1000), 3, '0'), 2 + MOD(n-1,1000)))
    ELSE CONCAT('C', LPAD(1 + MOD(n-1,4), 3, '0'))
  END,
  CONCAT(
    ELT(1 + MOD(n-1,8), 'Created','Submitted','Approved','Rejected','Updated','Assigned','Accessed','Removed'),
    ' ', LOWER(ELT(1 + MOD(n-1,6), 'User','Asset','Verification','Transaction','Borrowing','Category')), ' record'
  ),
  TIMESTAMP('2026-08-17 12:00:00') - INTERVAL MOD(n * 5, 365) DAY
FROM seq;

COMMIT;
