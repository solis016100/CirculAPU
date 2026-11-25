-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 29, 2026 at 05:11 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `circulapu_db`
--
CREATE DATABASE IF NOT EXISTS `circulapu_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `circulapu_db`;

-- --------------------------------------------------------

--
-- Table structure for table `achievementbadges`
--

CREATE TABLE `achievementbadges` (
  `achieveBadgeID` varchar(10) NOT NULL,
  `achieveBadgeName` varchar(50) NOT NULL,
  `achieveBadgeDescription` varchar(255) NOT NULL,
  `achieveBadgeRequirement` varchar(255) NOT NULL,
  `achieveBadgeIcon` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `achievementbadges`
--

INSERT INTO `achievementbadges` (`achieveBadgeID`, `achieveBadgeName`, `achieveBadgeDescription`, `achieveBadgeRequirement`, `achieveBadgeIcon`) VALUES
('AB001', 'Mission Starter', 'Completed a first sustainability mission.', 'Complete 1 mission', 'badge_mission_starter_1788016109.png'),
('AB002', 'Mission Explorer', 'Completed five sustainability missions.', 'Complete 5 missions', 'badge_mission_explorer.png'),
('AB003', 'Mission Master', 'Completed ten sustainability missions.', 'Complete 10 missions', 'badge_mission_master.png'),
('AB004', 'Eco Beginner', 'Earned the first 100 EcoPoints.', 'Reach 100 EcoPoints', 'badge_eco_beginner.png'),
('AB005', 'Eco Champion', 'Built a balance of 500 EcoPoints.', 'Reach 500 EcoPoints', 'badge_eco_champion.png'),
('AB006', 'Green Legend', 'Reached 1,000 EcoPoints through sustainable activity.', 'Reach 1,000 EcoPoints', 'badge_green_legend.png'),
('AB007', 'Reuse Starter', 'Published three reusable assets.', 'Get 3 approved assets', 'badge_reuse_starter.png'),
('AB008', 'Reliable Borrower', 'Completed five asset loans responsibly.', 'Borrow 5 assets', 'badge_reliable_borrower_1788015953.png'),
('AB009', 'Reward Collector', 'Redeemed three sustainability rewards.', 'Redeem 3 rewards', 'badge_reward_collector.png'),
('AB010', 'Reward Enthusiast', 'Redeemed 10 or more rewards.', 'Redeem 10 rewards', 'badge_reward_enthusiast_1788016048.png');

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `assetID` varchar(10) NOT NULL,
  `ownerID` varchar(10) NOT NULL,
  `categoryID` varchar(10) NOT NULL,
  `assetName` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `assetCondition` enum('New','Like New','Good','Fair','Poor') NOT NULL,
  `imageURL` varchar(255) DEFAULT NULL,
  `availabilityStatus` enum('Available','Reserved','Borrowed','Unavailable') NOT NULL,
  `listingStatus` enum('Draft','Pending Review','Ready for Verification','Approved','Published','Rejected','Removed') NOT NULL,
  `createdDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `verificationStatus` enum('Pending','Approved','Rejected') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`assetID`, `ownerID`, `categoryID`, `assetName`, `description`, `assetCondition`, `imageURL`, `availabilityStatus`, `listingStatus`, `createdDate`, `updatedDate`, `verificationStatus`) VALUES
('A001', 'U007', 'C001', 'Casio Scientific Calculator', 'Reliable calculator suitable for mathematics and engineering classes.', 'Good', 'uploads/assets/calculator.jpg', 'Available', 'Published', '2026-05-02 01:00:00', '2026-05-03 06:00:00', 'Approved'),
('A002', 'U008', 'C002', 'Database Systems Textbook', 'Database design and SQL reference book with light annotations.', 'Good', 'uploads/assets/database_book.jpg', 'Borrowed', 'Published', '2026-05-04 03:00:00', '2026-08-20 02:00:00', 'Approved'),
('A003', 'U009', 'C001', '65W USB-C Charger', 'Universal USB-C laptop charger with a two-metre cable.', 'Like New', 'uploads/assets/usb_charger.jpg', 'Reserved', 'Published', '2026-05-08 05:00:00', '2026-08-26 08:00:00', 'Approved'),
('A004', 'U010', 'C004', 'Badminton Racket Set', 'Two rackets and a reusable carry case.', 'Good', 'uploads/assets/badminton_racket.jpg', 'Available', 'Published', '2026-05-12 02:00:00', '2026-05-13 07:00:00', 'Approved'),
('A005', 'U011', 'C003', 'LED Study Lamp', 'Adjustable lamp with three brightness settings.', 'Good', 'uploads/assets/study_lamp.jpg', 'Unavailable', 'Rejected', '2026-05-15 08:00:00', '2026-05-16 03:00:00', 'Rejected'),
('A006', 'U012', 'C005', 'Phone Tripod', 'Compact tripod with Bluetooth shutter remote.', 'Like New', 'uploads/assets/phone_tripod.jpg', 'Available', 'Published', '2026-05-20 01:30:00', '2026-05-21 04:00:00', 'Approved'),
('A007', 'U013', 'C002', 'Academic Writing Guide', 'A practical guide to citations, structure, and academic style.', 'Good', 'uploads/assets/writing_guide.jpg', 'Unavailable', 'Ready for Verification', '2026-08-24 02:00:00', '2026-08-25 01:00:00', 'Pending'),
('A008', 'U007', 'C008', 'Precision Screwdriver Kit', 'Twenty-four-bit repair kit for electronics and small appliances.', 'Like New', 'uploads/assets/screwdriver_kit.jpg', 'Available', 'Published', '2026-06-01 05:00:00', '2026-06-02 02:00:00', 'Approved'),
('A009', 'U008', 'C009', 'Reusable Meal Container Set', 'Three food-safe containers with secure lids.', 'Good', 'uploads/assets/container_set.jpg', 'Borrowed', 'Published', '2026-06-04 07:00:00', '2026-08-10 01:00:00', 'Approved'),
('A010', 'U009', 'C006', 'Four-Socket Extension Lead', 'Surge-protected extension lead for student events.', 'Good', 'uploads/assets/extension_lead.jpg', 'Available', 'Published', '2026-06-07 01:00:00', '2026-06-08 06:00:00', 'Approved'),
('A011', 'U010', 'C007', 'Black Formal Blazer', 'Medium-size blazer suitable for presentations and interviews.', 'Good', 'uploads/assets/formal_blazer.jpg', 'Available', 'Published', '2026-06-10 04:00:00', '2026-06-11 07:00:00', 'Approved'),
('A012', 'U011', 'C001', 'Wireless Presentation Clicker', 'USB presentation remote with laser pointer.', 'Good', 'uploads/assets/presentation_clicker.jpg', 'Reserved', 'Published', '2026-06-14 01:00:00', '2026-08-27 06:00:00', 'Approved'),
('A013', 'U012', 'C004', 'Yoga Mat', 'Clean non-slip exercise mat with carrying strap.', 'Like New', 'uploads/assets/yoga_mat.jpg', 'Available', 'Published', '2026-06-18 03:00:00', '2026-06-19 02:00:00', 'Approved'),
('A014', 'U013', 'C005', 'A3 Cutting Mat', 'Self-healing cutting mat for design projects.', 'Good', 'uploads/assets/cutting_mat.jpg', 'Unavailable', 'Pending Review', '2026-08-27 03:00:00', NULL, 'Pending'),
('A015', 'U007', 'C002', 'Discrete Mathematics Notes', 'Organised revision notes covering core discrete mathematics topics.', 'Good', 'uploads/assets/discrete_math_notes.jpg', 'Available', 'Published', '2026-07-02 06:00:00', '2026-07-03 05:00:00', 'Approved'),
('A016', 'U008', 'C003', 'Foldable Laptop Table', 'Portable table for study use in dorm rooms.', 'Fair', 'uploads/assets/laptop_table.jpg', 'Available', 'Published', '2026-07-05 02:00:00', '2026-07-06 01:00:00', 'Approved'),
('A017', 'U009', 'C005', 'USB Condenser Microphone', 'Entry-level microphone for podcasts and presentations.', 'Good', 'uploads/assets/usb_microphone.jpg', 'Borrowed', 'Published', '2026-07-08 08:00:00', '2026-08-15 04:00:00', 'Approved'),
('A018', 'U010', 'C006', 'Reusable Event Bunting', 'Ten metres of washable fabric bunting.', 'Like New', 'uploads/assets/event_bunting.jpg', 'Available', 'Published', '2026-07-12 01:00:00', '2026-07-13 03:00:00', 'Approved'),
('A019', 'U011', 'C008', 'Bicycle Repair Pump', 'Portable floor pump with multiple valve adapters.', 'Good', 'uploads/assets/bicycle_pump.jpg', 'Available', 'Published', '2026-07-17 05:30:00', '2026-07-18 02:30:00', 'Approved'),
('A020', 'U012', 'C001', 'HDMI Adapter Set', 'USB-C and Mini DisplayPort adapters for classroom displays.', 'Good', 'uploads/assets/hdmi_adapters.jpg', 'Unavailable', 'Ready for Verification', '2026-08-23 07:00:00', '2026-08-24 02:00:00', 'Pending'),
('A021', 'U013', 'C004', 'Resistance Band Set', 'Five resistance bands with different strength levels.', 'New', 'uploads/assets/resistance_bands.jpg', 'Available', 'Published', '2026-07-24 02:00:00', '2026-07-25 01:00:00', 'Approved'),
('A022', 'U007', 'C009', 'Insulated Flask', 'One-litre stainless steel flask for group activities.', 'Good', 'uploads/assets/insulated_flask.jpg', 'Available', 'Published', '2026-07-29 04:00:00', '2026-07-30 03:00:00', 'Approved'),
('A023', 'U008', 'C007', 'Graduation Gown', 'Clean medium-size gown for photography sessions.', 'Good', 'uploads/assets/graduation_gown.jpg', 'Unavailable', 'Draft', '2026-08-27 09:00:00', NULL, 'Pending'),
('A024', 'U009', 'C003', 'Damaged Electric Kettle', 'Kettle submitted with a loose power connector.', 'Poor', 'uploads/assets/electric_kettle.jpg', 'Unavailable', 'Rejected', '2026-08-18 02:00:00', '2026-08-19 06:00:00', 'Rejected');

-- --------------------------------------------------------

--
-- Table structure for table `assetstatushistory`
--

CREATE TABLE `assetstatushistory` (
  `historyID` varchar(10) NOT NULL,
  `assetID` varchar(10) NOT NULL,
  `updatedBy` varchar(10) NOT NULL,
  `oldAvailabilityStatus` enum('Available','Reserved','Borrowed','Unavailable') NOT NULL,
  `newAvailabilityStatus` enum('Available','Reserved','Borrowed','Unavailable') NOT NULL,
  `statusChangeReason` varchar(255) DEFAULT NULL,
  `updatedDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assetstatushistory`
--

INSERT INTO `assetstatushistory` (`historyID`, `assetID`, `updatedBy`, `oldAvailabilityStatus`, `newAvailabilityStatus`, `statusChangeReason`, `updatedDate`) VALUES
('H001', 'A001', 'U005', 'Unavailable', 'Available', 'Asset approved and published.', '2026-05-03 06:00:00'),
('H002', 'A002', 'U005', 'Unavailable', 'Available', 'Asset approved and published.', '2026-05-05 04:00:00'),
('H003', 'A002', 'U003', 'Available', 'Reserved', 'Borrowing request approved.', '2026-08-20 01:30:00'),
('H004', 'A002', 'U003', 'Reserved', 'Borrowed', 'Borrower pickup confirmed.', '2026-08-20 02:00:00'),
('H005', 'A003', 'U006', 'Unavailable', 'Available', 'Asset approved and published.', '2026-05-09 02:00:00'),
('H006', 'A003', 'U003', 'Available', 'Reserved', 'Asset reserved for approved request.', '2026-08-26 08:00:00'),
('H007', 'A004', 'U005', 'Unavailable', 'Available', 'Asset approved and published.', '2026-05-13 07:00:00'),
('H008', 'A005', 'U006', 'Unavailable', 'Unavailable', 'Listing rejected for safety reasons.', '2026-05-16 03:00:00'),
('H009', 'A006', 'U005', 'Unavailable', 'Available', 'Asset approved and published.', '2026-05-21 04:00:00'),
('H010', 'A008', 'U005', 'Unavailable', 'Available', 'Asset approved and published.', '2026-06-02 02:00:00'),
('H011', 'A009', 'U006', 'Unavailable', 'Available', 'Asset approved and published.', '2026-06-05 06:00:00'),
('H012', 'A009', 'U003', 'Available', 'Reserved', 'Borrowing request approved.', '2026-08-10 02:30:00'),
('H013', 'A009', 'U003', 'Reserved', 'Borrowed', 'Borrower pickup confirmed.', '2026-08-10 03:00:00'),
('H014', 'A010', 'U005', 'Unavailable', 'Available', 'Asset approved and published.', '2026-06-08 06:00:00'),
('H015', 'A011', 'U006', 'Unavailable', 'Available', 'Asset approved and published.', '2026-06-11 07:00:00'),
('H016', 'A012', 'U005', 'Unavailable', 'Available', 'Asset approved and published.', '2026-06-15 05:00:00'),
('H017', 'A012', 'U004', 'Available', 'Reserved', 'Asset reserved for approved request.', '2026-08-27 06:00:00'),
('H018', 'A013', 'U006', 'Unavailable', 'Available', 'Asset approved and published.', '2026-06-19 02:00:00'),
('H019', 'A015', 'U005', 'Unavailable', 'Available', 'Asset approved and published.', '2026-07-03 05:00:00'),
('H020', 'A016', 'U006', 'Unavailable', 'Available', 'Asset approved and published.', '2026-07-06 01:00:00'),
('H021', 'A017', 'U005', 'Unavailable', 'Available', 'Asset approved and published.', '2026-07-09 06:00:00'),
('H022', 'A017', 'U004', 'Available', 'Reserved', 'Borrowing request approved.', '2026-08-15 03:30:00'),
('H023', 'A017', 'U004', 'Reserved', 'Borrowed', 'Borrower pickup confirmed.', '2026-08-15 04:00:00'),
('H024', 'A018', 'U006', 'Unavailable', 'Available', 'Asset approved and published.', '2026-07-13 03:00:00'),
('H025', 'A019', 'U005', 'Unavailable', 'Available', 'Asset approved and published.', '2026-07-18 02:30:00'),
('H026', 'A021', 'U006', 'Unavailable', 'Available', 'Asset approved and published.', '2026-07-25 01:00:00'),
('H027', 'A022', 'U005', 'Unavailable', 'Available', 'Asset approved and published.', '2026-07-30 03:00:00'),
('H028', 'A024', 'U006', 'Unavailable', 'Unavailable', 'Listing rejected due to electrical risk.', '2026-08-19 06:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `borrowings`
--

CREATE TABLE `borrowings` (
  `borrowID` varchar(10) NOT NULL,
  `assetID` varchar(10) NOT NULL,
  `borrowerID` varchar(10) NOT NULL,
  `transactionID` varchar(10) NOT NULL,
  `borrowDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `expectedReturnDate` datetime NOT NULL,
  `actualReturnDate` datetime DEFAULT NULL,
  `borrowingStatus` enum('Pending','Approved','Borrowed','Returned','Completed','Cancelled','Overdue') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowings`
--

INSERT INTO `borrowings` (`borrowID`, `assetID`, `borrowerID`, `transactionID`, `borrowDate`, `expectedReturnDate`, `actualReturnDate`, `borrowingStatus`) VALUES
('B001', 'A001', 'U008', 'TR001', '2026-06-04 01:00:00', '2026-06-11 17:00:00', '2026-06-11 14:00:00', 'Completed'),
('B002', 'A004', 'U009', 'TR002', '2026-06-11 01:00:00', '2026-06-18 17:00:00', '2026-06-18 16:00:00', 'Completed'),
('B003', 'A006', 'U010', 'TR003', '2026-06-21 02:00:00', '2026-06-24 17:00:00', '2026-06-24 15:00:00', 'Completed'),
('B004', 'A008', 'U011', 'TR004', '2026-07-02 01:00:00', '2026-07-05 17:00:00', '2026-07-05 12:00:00', 'Completed'),
('B005', 'A010', 'U012', 'TR005', '2026-07-09 00:00:00', '2026-07-11 18:00:00', '2026-07-11 16:00:00', 'Completed'),
('B006', 'A013', 'U007', 'TR006', '2026-07-16 02:00:00', '2026-07-20 17:00:00', '2026-07-20 13:00:00', 'Completed'),
('B007', 'A002', 'U009', 'TR007', '2026-08-20 02:00:00', '2026-09-03 17:00:00', NULL, 'Borrowed'),
('B008', 'A009', 'U011', 'TR008', '2026-08-10 03:00:00', '2026-08-17 17:00:00', NULL, 'Overdue'),
('B009', 'A017', 'U013', 'TR009', '2026-08-15 04:00:00', '2026-08-30 17:00:00', NULL, 'Borrowed'),
('B010', 'A003', 'U010', 'TR010', '2026-08-29 02:00:00', '2026-09-05 17:00:00', NULL, 'Approved'),
('B011', 'A012', 'U008', 'TR011', '2026-08-31 01:00:00', '2026-09-02 17:00:00', NULL, 'Approved'),
('B012', 'A016', 'U013', 'TR012', '2026-09-01 01:00:00', '2026-09-08 17:00:00', NULL, 'Pending'),
('B013', 'A019', 'U007', 'TR013', '2026-08-30 02:00:00', '2026-08-31 17:00:00', NULL, 'Pending'),
('B014', 'A021', 'U012', 'TR014', '2026-08-27 01:00:00', '2026-09-03 17:00:00', NULL, 'Cancelled'),
('B015', 'A022', 'U011', 'TR015', '2026-08-26 01:00:00', '2026-09-01 17:00:00', NULL, 'Cancelled'),
('B016', 'A015', 'U010', 'TR016', '2026-05-21 01:00:00', '2026-05-28 17:00:00', '2026-05-27 15:00:00', 'Completed'),
('B017', 'A018', 'U008', 'TR017', '2026-07-23 01:00:00', '2026-07-25 18:00:00', '2026-07-25 17:00:00', 'Completed'),
('B018', 'A011', 'U009', 'TR018', '2026-09-02 01:00:00', '2026-09-05 17:00:00', NULL, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `categoryID` varchar(10) NOT NULL,
  `categoryName` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `categoryIcon` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `createdDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`categoryID`, `categoryName`, `description`, `categoryIcon`, `status`, `createdDate`) VALUES
('C001', 'Electronics', 'Laptops, chargers, calculators, and useful electronic accessories.', 'assets/icons/category-electronics.png', 'Active', '2026-01-05 02:00:00'),
('C002', 'Books & Stationery', 'Textbooks, reference books, notebooks, and study supplies.', 'assets/icons/category-books.png', 'Active', '2026-01-05 02:05:00'),
('C003', 'Household & Living', 'Safe and reusable essentials for dormitory and daily living.', 'assets/icons/category-furniture.png', 'Active', '2026-01-05 02:10:00'),
('C004', 'Sports Equipment', 'Rackets, balls, fitness accessories, and outdoor equipment.', 'assets/icons/category-sports.png', 'Active', '2026-01-05 02:15:00'),
('C005', 'Creative & Media', 'Cameras, tripods, drawing tools, and media production equipment.', 'assets/icons/category-music.png', 'Active', '2026-01-05 02:20:00'),
('C006', 'Event Supplies', 'Reusable decorations, signs, extension leads, and event equipment.', 'assets/icons/category-others.png', 'Active', '2026-01-05 02:25:00'),
('C007', 'Clothing & Accessories', 'Clean reusable clothing, formal wear, and practical accessories.', 'assets/icons/category-others.png', 'Active', '2026-01-05 02:30:00'),
('C008', 'Tools & Repair', 'Basic hand tools and repair kits for responsible maintenance.', 'assets/icons/category-others.png', 'Active', '2026-01-05 02:35:00'),
('C009', 'Kitchen & Dining', 'Reusable containers, utensils, and small dining accessories.', 'assets/icons/category-furniture.png', 'Active', '2026-01-05 02:40:00'),
('C010', 'Legacy Accessories', 'Archived category retained for historical reporting.', 'assets/icons/category-others.png', 'Inactive', '2026-01-05 02:45:00');

-- --------------------------------------------------------

--
-- Table structure for table `ecopointstransaction`
--

CREATE TABLE `ecopointstransaction` (
  `transactionID` varchar(10) NOT NULL,
  `userID` varchar(10) NOT NULL,
  `points` int(11) NOT NULL,
  `transactionType` varchar(50) NOT NULL,
  `sourceType` varchar(50) NOT NULL,
  `referenceID` varchar(10) DEFAULT NULL,
  `transactionDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ecopointstransaction`
--

INSERT INTO `ecopointstransaction` (`transactionID`, `userID`, `points`, `transactionType`, `sourceType`, `referenceID`, `transactionDate`) VALUES
('ET001', 'U007', 50, 'Earned', 'Mission', 'MS001', '2026-05-05 07:05:00'),
('ET002', 'U007', 100, 'Earned', 'Mission', 'MS002', '2026-05-18 08:05:00'),
('ET003', 'U007', 100, 'Earned', 'Mission', 'MS003', '2026-06-02 06:05:00'),
('ET004', 'U007', 100, 'Earned', 'Mission', 'MS004', '2026-07-10 09:05:00'),
('ET005', 'U007', 120, 'Earned', 'Borrowing', 'TR006', '2026-07-20 05:05:00'),
('ET006', 'U007', -250, 'Spent', 'RewardRedemption', 'RR001', '2026-07-22 02:00:00'),
('ET007', 'U008', 50, 'Earned', 'Mission', 'MS005', '2026-05-07 07:05:00'),
('ET008', 'U008', 50, 'Earned', 'Mission', 'MS006', '2026-05-22 09:05:00'),
('ET009', 'U008', 75, 'Earned', 'Mission', 'MS007', '2026-06-09 02:05:00'),
('ET010', 'U008', 80, 'Earned', 'Borrowing', 'TR001', '2026-06-11 06:05:00'),
('ET011', 'U008', -100, 'Spent', 'RewardRedemption', 'RR002', '2026-06-15 02:00:00'),
('ET012', 'U008', -150, 'Spent', 'RewardRedemption', 'RR003', '2026-07-02 03:00:00'),
('ET013', 'U009', 150, 'Earned', 'Mission', 'MS009', '2026-06-01 08:05:00'),
('ET014', 'U009', 80, 'Earned', 'Borrowing', 'TR002', '2026-06-18 08:05:00'),
('ET015', 'U009', -100, 'Spent', 'RewardRedemption', 'RR004', '2026-07-01 04:00:00'),
('ET016', 'U010', 50, 'Earned', 'Mission', 'MS012', '2026-04-20 06:05:00'),
('ET017', 'U010', 100, 'Earned', 'Mission', 'MS013', '2026-05-02 08:05:00'),
('ET018', 'U010', 150, 'Earned', 'Mission', 'MS014', '2026-05-16 06:05:00'),
('ET019', 'U010', 100, 'Earned', 'Mission', 'MS015', '2026-06-04 09:05:00'),
('ET020', 'U010', 100, 'Earned', 'Mission', 'MS016', '2026-06-21 06:05:00'),
('ET021', 'U010', 100, 'Earned', 'Mission', 'MS017', '2026-07-12 09:05:00'),
('ET022', 'U010', 80, 'Earned', 'Borrowing', 'TR003', '2026-06-24 07:05:00'),
('ET023', 'U010', 80, 'Earned', 'Borrowing', 'TR016', '2026-05-27 07:05:00'),
('ET024', 'U010', -250, 'Spent', 'RewardRedemption', 'RR005', '2026-07-20 02:00:00'),
('ET025', 'U010', -200, 'Spent', 'RewardRedemption', 'RR006', '2026-08-01 01:00:00'),
('ET026', 'U011', 50, 'Earned', 'Mission', 'MS018', '2026-06-09 07:05:00'),
('ET027', 'U011', 80, 'Earned', 'Borrowing', 'TR004', '2026-07-05 04:05:00'),
('ET028', 'U011', -100, 'Spent', 'RewardRedemption', 'RR007', '2026-07-10 06:00:00'),
('ET029', 'U012', 50, 'Earned', 'Mission', 'MS020', '2026-05-11 07:05:00'),
('ET030', 'U012', 75, 'Earned', 'Mission', 'MS021', '2026-06-13 09:05:00'),
('ET031', 'U012', 75, 'Earned', 'Mission', 'MS022', '2026-07-06 08:05:00'),
('ET032', 'U012', 80, 'Earned', 'Borrowing', 'TR005', '2026-07-11 08:05:00'),
('ET033', 'U012', -180, 'Spent', 'RewardRedemption', 'RR008', '2026-07-15 03:00:00'),
('ET034', 'U013', 50, 'Earned', 'Mission', 'MS023', '2026-07-15 09:05:00'),
('ET035', 'U013', -100, 'Spent', 'RewardRedemption', 'RR009', '2026-07-20 04:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `missions`
--

CREATE TABLE `missions` (
  `missionID` varchar(10) NOT NULL,
  `missionName` varchar(50) NOT NULL,
  `missionDescription` varchar(255) NOT NULL,
  `missionRequirement` varchar(255) NOT NULL,
  `ecoPointsReward` int(11) NOT NULL DEFAULT 0,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `missions`
--

INSERT INTO `missions` (`missionID`, `missionName`, `missionDescription`, `missionRequirement`, `ecoPointsReward`, `status`) VALUES
('M001', 'Bring Your Own Bottle', 'Use a reusable water bottle on campus.', 'Upload a photo at an APU refill station', 50, 'Inactive'),
('M002', 'E-Waste Recycling Drive', 'Dispose of batteries or electronics responsibly.', 'Upload a photo at an approved e-waste point', 100, 'Active'),
('M003', 'Campus Clean Walk', 'Collect litter while walking around campus.', 'Upload before-and-after cleanup evidence', 150, 'Active'),
('M004', 'Reusable Bag Day', 'Avoid disposable shopping bags for one day.', 'Upload a photo of your reusable bag in use', 50, 'Active'),
('M005', 'Paperless Study Day', 'Complete a full study day using digital materials.', 'Upload a screenshot of digital notes', 75, 'Active'),
('M006', 'Donate an Item', 'Give a usable item a second life through donation.', 'Upload a photo and donation confirmation', 100, 'Active'),
('M007', 'Green Transport Day', 'Travel using public transport, cycling, or walking.', 'Upload a ticket or activity screenshot', 100, 'Active'),
('M008', 'Zero Food Waste Meal', 'Finish a meal without wasting edible food.', 'Upload meal photos before and after', 75, 'Active'),
('M009', 'Reusable Container Challenge', 'Use a reusable container for takeaway food.', 'Upload a photo of the container in use', 75, 'Active'),
('M010', 'Share a Green Tip', 'Share a practical sustainability tip with the community.', 'Upload a screenshot of your published tip', 100, 'Active'),
('M011', 'Switch Off and Save', 'Reduce unnecessary electricity use in a shared space.', 'Upload evidence of lights or equipment switched off', 60, 'Active'),
('M012', 'Old Clothes Swap', 'Exchange wearable clothing instead of discarding it.', 'Upload a photo from a verified clothing swap', 120, 'Inactive');

-- --------------------------------------------------------

--
-- Table structure for table `missionsubmission`
--

CREATE TABLE `missionsubmission` (
  `submissionID` varchar(10) NOT NULL,
  `userID` varchar(10) NOT NULL,
  `missionID` varchar(10) NOT NULL,
  `evidencePath` varchar(255) NOT NULL,
  `submissionDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `submissionStatus` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `missionsubmission`
--

INSERT INTO `missionsubmission` (`submissionID`, `userID`, `missionID`, `evidencePath`, `submissionDate`, `submissionStatus`) VALUES
('MS001', 'U007', 'M001', 'uploads/submissions/U007_M001.jpg', '2026-05-05 01:00:00', 'Approved'),
('MS002', 'U007', 'M002', 'uploads/submissions/U007_M002.jpg', '2026-05-18 03:00:00', 'Approved'),
('MS003', 'U007', 'M007', 'uploads/submissions/U007_M007.jpg', '2026-06-02 00:30:00', 'Approved'),
('MS004', 'U007', 'M010', 'uploads/submissions/U007_M010.jpg', '2026-07-10 05:00:00', 'Approved'),
('MS005', 'U008', 'M001', 'uploads/submissions/U008_M001.jpg', '2026-05-07 02:00:00', 'Approved'),
('MS006', 'U008', 'M004', 'uploads/submissions/U008_M004.jpg', '2026-05-22 06:00:00', 'Approved'),
('MS007', 'U008', 'M005', 'uploads/submissions/U008_M005.jpg', '2026-06-08 08:00:00', 'Approved'),
('MS008', 'U008', 'M008', 'uploads/submissions/U008_M008.jpg', '2026-08-27 03:00:00', 'Pending'),
('MS009', 'U009', 'M003', 'uploads/submissions/U009_M003.jpg', '2026-06-01 01:30:00', 'Approved'),
('MS010', 'U009', 'M006', 'uploads/submissions/U009_M006.jpg', '2026-06-18 04:00:00', 'Rejected'),
('MS011', 'U009', 'M011', 'uploads/submissions/U009_M011.jpg', '2026-08-26 09:00:00', 'Pending'),
('MS012', 'U010', 'M001', 'uploads/submissions/U010_M001.jpg', '2026-04-20 00:00:00', 'Approved'),
('MS013', 'U010', 'M002', 'uploads/submissions/U010_M002.jpg', '2026-05-02 02:00:00', 'Approved'),
('MS014', 'U010', 'M003', 'uploads/submissions/U010_M003.jpg', '2026-05-16 01:00:00', 'Approved'),
('MS015', 'U010', 'M006', 'uploads/submissions/U010_M006.jpg', '2026-06-04 06:00:00', 'Approved'),
('MS016', 'U010', 'M007', 'uploads/submissions/U010_M007.jpg', '2026-06-21 00:00:00', 'Approved'),
('MS017', 'U010', 'M010', 'uploads/submissions/U010_M010.jpg', '2026-07-12 07:00:00', 'Approved'),
('MS018', 'U011', 'M004', 'uploads/submissions/U011_M004.jpg', '2026-06-09 03:00:00', 'Approved'),
('MS019', 'U011', 'M009', 'uploads/submissions/U011_M009.jpg', '2026-08-25 04:00:00', 'Rejected'),
('MS020', 'U012', 'M001', 'uploads/submissions/U012_M001.jpg', '2026-05-11 01:00:00', 'Approved'),
('MS021', 'U012', 'M005', 'uploads/submissions/U012_M005.jpg', '2026-06-13 05:00:00', 'Approved'),
('MS022', 'U012', 'M008', 'uploads/submissions/U012_M008.jpg', '2026-07-06 04:00:00', 'Approved'),
('MS023', 'U013', 'M004', 'uploads/submissions/U013_M004.jpg', '2026-07-15 07:00:00', 'Approved'),
('MS024', 'U013', 'M011', 'uploads/submissions/U013_M011.jpg', '2026-08-28 00:30:00', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `notificationID` varchar(10) NOT NULL,
  `userID` varchar(10) NOT NULL,
  `title` varchar(100) NOT NULL,
  `content` varchar(255) NOT NULL,
  `status` enum('Read','Unread') NOT NULL DEFAULT 'Unread',
  `createdDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification`
--

INSERT INTO `notification` (`notificationID`, `userID`, `title`, `content`, `status`, `createdDate`) VALUES
('N001', 'U003', 'New borrowing request', 'TR012 is waiting for manager review.', 'Unread', '2026-08-27 02:00:00'),
('N002', 'U003', 'New borrowing request', 'TR013 is waiting for manager review.', 'Unread', '2026-08-27 04:30:00'),
('N003', 'U004', 'New borrowing request', 'TR018 is waiting for manager review.', 'Unread', '2026-08-28 00:00:00'),
('N004', 'U005', 'Asset ready for verification', 'A007 has passed manager review.', 'Unread', '2026-08-25 01:00:00'),
('N005', 'U006', 'Asset ready for verification', 'A020 has passed manager review.', 'Read', '2026-08-24 02:00:00'),
('N006', 'U005', 'Mission evidence submitted', 'MS008 is waiting for verification.', 'Unread', '2026-08-27 03:00:00'),
('N007', 'U006', 'Mission evidence submitted', 'MS011 is waiting for verification.', 'Unread', '2026-08-26 09:00:00'),
('N008', 'U005', 'Mission evidence submitted', 'MS024 is waiting for verification.', 'Unread', '2026-08-28 00:30:00'),
('N009', 'U007', 'Reward redeemed', 'Your Reusable Water Bottle redemption was recorded.', 'Read', '2026-07-22 02:00:00'),
('N010', 'U008', 'Borrowing completed', 'The calculator loan was completed successfully.', 'Read', '2026-06-11 06:00:00'),
('N011', 'U009', 'Request approved', 'Database Systems Textbook is ready and on loan.', 'Read', '2026-08-20 02:00:00'),
('N012', 'U010', 'Pickup scheduled', 'USB-C Charger is reserved for pickup.', 'Unread', '2026-08-26 08:00:00'),
('N013', 'U011', 'Overdue reminder', 'Reusable Meal Container Set is overdue. Please return it promptly.', 'Unread', '2026-08-18 01:00:00'),
('N014', 'U012', 'Request rejected', 'Your Resistance Band Set request was not approved.', 'Read', '2026-08-26 07:00:00'),
('N015', 'U013', 'Asset sent for verification', 'Academic Writing Guide is awaiting auditor review.', 'Unread', '2026-08-25 01:00:00'),
('N016', 'U007', 'Badge awarded', 'You earned the Reward Collector badge.', 'Unread', '2026-08-08 07:10:00'),
('N017', 'U008', 'Badge awarded', 'You earned the Eco Beginner badge.', 'Read', '2026-06-09 02:10:00'),
('N018', 'U009', 'Mission approved', 'Campus Clean Walk was approved for 150 EcoPoints.', 'Read', '2026-06-01 08:05:00'),
('N019', 'U010', 'Tier upgraded', 'You reached the Eco Legend tier.', 'Unread', '2026-08-01 01:15:00'),
('N020', 'U011', 'Mission rejected', 'Reusable Container Challenge evidence needs improvement.', 'Unread', '2026-08-25 08:00:00'),
('N021', 'U012', 'Reward redeemed', 'Your Eco Shopping Bag redemption was recorded.', 'Read', '2026-07-15 03:00:00'),
('N022', 'U013', 'Mission approved', 'Reusable Bag Day was approved for 50 EcoPoints.', 'Read', '2026-07-15 09:05:00'),
('N023', 'U001', 'New report submitted', 'REP002 is waiting in the reports queue.', 'Unread', '2026-08-26 01:00:00'),
('N024', 'U002', 'New report submitted', 'REP003 is waiting in the reports queue.', 'Unread', '2026-08-28 00:00:00'),
('N025', 'U001', 'Report requires review', 'REP006 concerns a pending asset verification.', 'Unread', '2026-08-27 02:00:00'),
('N026', 'U003', 'Asset listing submitted', 'A014 is waiting for initial manager review.', 'Unread', '2026-08-27 03:00:00'),
('N027', 'U004', 'Asset listing submitted', 'A023 remains a draft and needs no action yet.', 'Read', '2026-08-27 09:00:00'),
('N028', 'U007', 'New mission available', 'Switch Off and Save is available for 60 EcoPoints.', 'Unread', '2026-08-01 02:00:00'),
('N029', 'U008', 'New mission available', 'Switch Off and Save is available for 60 EcoPoints.', 'Read', '2026-08-01 02:00:00'),
('N030', 'U009', 'New mission available', 'Switch Off and Save is available for 60 EcoPoints.', 'Unread', '2026-08-01 02:00:00'),
('N031', 'U010', 'New reward available', 'The Seedling Starter Kit is now available.', 'Read', '2026-08-02 02:00:00'),
('N032', 'U011', 'New reward available', 'The Seedling Starter Kit is now available.', 'Unread', '2026-08-02 02:00:00'),
('N033', 'U012', 'New reward available', 'The Seedling Starter Kit is now available.', 'Unread', '2026-08-02 02:00:00'),
('N034', 'U013', 'Request cancelled', 'Your flask request TR015 was cancelled.', 'Read', '2026-08-25 06:00:00'),
('N035', 'U003', 'Overdue asset', 'TR008 is overdue and needs follow-up.', 'Unread', '2026-08-18 01:00:00'),
('N036', 'U004', 'Upcoming pickup', 'TR010 is scheduled for pickup on 29 August.', 'Read', '2026-08-27 01:00:00'),
('N037', 'U005', 'Report escalation', 'REP001 references a rejected mission verification.', 'Read', '2026-06-19 01:00:00'),
('N038', 'U006', 'Report escalation', 'REP008 requests clarification on an asset rejection.', 'Unread', '2026-08-20 07:00:00'),
('N039', 'U007', 'Asset approved', 'Your Insulated Flask listing is published.', 'Read', '2026-07-30 03:00:00'),
('N040', 'U012', 'Asset pending verification', 'HDMI Adapter Set is awaiting auditor review.', 'Unread', '2026-08-24 02:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `report`
--

CREATE TABLE `report` (
  `reportID` varchar(10) NOT NULL,
  `reporterID` varchar(10) NOT NULL,
  `verificationID` varchar(10) DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `evidenceImage` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Under Investigation','Resolved') NOT NULL DEFAULT 'Pending',
  `timeCreated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `report`
--

INSERT INTO `report` (`reportID`, `reporterID`, `verificationID`, `reason`, `evidenceImage`, `status`, `timeCreated`) VALUES
('REP001', 'U009', 'V019', 'The donation evidence was rejected; requester asks for a second review.', 'uploads/reports/rep001.jpg', 'Under Investigation', '2026-06-19 01:00:00'),
('REP002', 'U011', 'V028', 'Member submitted clearer evidence for the reusable container mission.', 'uploads/reports/rep002.jpg', 'Pending', '2026-08-26 01:00:00'),
('REP003', 'U013', 'V007', 'Asset verification has remained pending longer than expected.', NULL, 'Pending', '2026-08-28 00:00:00'),
('REP004', 'U007', 'V003', 'Charger listing description may not identify the correct connector standard.', 'uploads/reports/rep004.jpg', 'Resolved', '2026-07-01 04:00:00'),
('REP005', 'U008', 'V002', 'Textbook condition changed after the latest return.', 'uploads/reports/rep005.jpg', 'Under Investigation', '2026-08-22 06:00:00'),
('REP006', 'U012', 'V008', 'HDMI adapter verification needs an update before an upcoming event.', NULL, 'Pending', '2026-08-27 02:00:00'),
('REP007', 'U010', 'V005', 'Rejected lamp remains visible in historical search results.', 'uploads/reports/rep007.jpg', 'Resolved', '2026-06-01 03:00:00'),
('REP008', 'U013', 'V009', 'Requesting clarification on the electrical safety rejection.', 'uploads/reports/rep008.jpg', 'Pending', '2026-08-20 07:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `rewardredemption`
--

CREATE TABLE `rewardredemption` (
  `redemptionID` varchar(10) NOT NULL,
  `userID` varchar(10) NOT NULL,
  `rewardID` varchar(10) NOT NULL,
  `redemptionDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rewardredemption`
--

INSERT INTO `rewardredemption` (`redemptionID`, `userID`, `rewardID`, `redemptionDate`) VALUES
('RR001', 'U007', 'R003', '2026-07-22 02:00:00'),
('RR002', 'U008', 'R001', '2026-06-15 02:00:00'),
('RR003', 'U008', 'R004', '2026-07-02 03:00:00'),
('RR004', 'U009', 'R001', '2026-07-01 04:00:00'),
('RR005', 'U010', 'R009', '2026-07-20 02:00:00'),
('RR006', 'U010', 'R002', '2026-08-01 01:00:00'),
('RR007', 'U011', 'R001', '2026-07-10 06:00:00'),
('RR008', 'U012', 'R007', '2026-07-15 03:00:00'),
('RR009', 'U013', 'R001', '2026-07-20 04:00:00'),
('RR010', 'U007', 'R004', '2026-08-02 02:00:00'),
('RR011', 'U007', 'R005', '2026-08-08 07:00:00'),
('RR012', 'U010', 'R010', '2026-08-10 03:00:00'),
('RR013', 'U012', 'R004', '2026-08-12 05:00:00'),
('RR014', 'U008', 'R007', '2026-08-16 02:00:00'),
('RR015', 'U009', 'R004', '2026-08-18 01:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `rewards`
--

CREATE TABLE `rewards` (
  `rewardID` varchar(10) NOT NULL,
  `rewardName` varchar(50) NOT NULL,
  `rewardDescription` varchar(255) NOT NULL,
  `rewardImage` varchar(255) DEFAULT NULL,
  `ecoPointsCost` int(11) NOT NULL DEFAULT 0,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `availability` enum('Available','Unavailable') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rewards`
--

INSERT INTO `rewards` (`rewardID`, `rewardName`, `rewardDescription`, `rewardImage`, `ecoPointsCost`, `quantity`, `availability`) VALUES
('R001', 'Campus Cafe RM5 Voucher', 'RM5 discount at participating campus cafes.', 'reward_cafe_rm5.png', 100, 42, 'Available'),
('R002', 'CirculAPU Tote Bag', 'Durable reusable tote bag with CirculAPU branding.', 'reward_tote_bag.png', 200, 24, 'Available'),
('R003', 'Reusable Water Bottle', 'BPA-free bottle for daily campus use.', 'reward_reusable_water_bottle_1788016160.png', 250, 18, 'Available'),
('R004', 'Recycled Notebook', 'Notebook produced with recycled paper.', 'reward_notebook.png', 150, 31, 'Available'),
('R005', 'Reusable Coffee Cup', 'Leak-resistant cup for hot and cold beverages.', 'reward_coffee_cup.png', 200, 16, 'Available'),
('R006', 'APU Stationery Set', 'A study set made with lower-impact materials.', 'reward_apu_stationery_set_1788016196.png', 350, 0, 'Unavailable'),
('R007', 'Eco Shopping Bag', 'Foldable shopping bag for everyday errands.', 'reward_shopping_bag.png', 180, 27, 'Available'),
('R008', 'Reusable Lunch Box', 'Food-safe lunch box that replaces disposable packaging.', 'reward_lunch_box.png', 220, 13, 'Available'),
('R009', 'Campus Cafe RM10 Voucher', 'RM10 discount at participating campus cafes.', 'reward_cafe_rm10.png', 250, 15, 'Available'),
('R010', 'Seedling Starter Kit', 'A compact herb-growing kit for beginners.', 'reward_seedling_kit.png', 300, 9, 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `systemlog`
--

CREATE TABLE `systemlog` (
  `logID` varchar(10) NOT NULL,
  `userID` varchar(10) NOT NULL,
  `actionType` enum('Create','Submit','Approve','Reject','Redeem','Assign','Update','Delete','Login','Logout') NOT NULL,
  `refType` enum('Mission','Asset','Reward','Badge','Tier','Verification','User','Category','Transaction','Borrowing') NOT NULL,
  `refID` varchar(10) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `timeLogged` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `systemlog`
--

INSERT INTO `systemlog` (`logID`, `userID`, `actionType`, `refType`, `refID`, `description`, `timeLogged`) VALUES
('LOG001', 'U007', 'Create', 'Asset', 'A001', 'Created asset listing Casio Scientific Calculator.', '2026-05-02 01:00:00'),
('LOG002', 'U005', 'Approve', 'Verification', 'V001', 'Approved calculator asset verification.', '2026-05-03 06:00:00'),
('LOG003', 'U008', 'Create', 'Asset', 'A002', 'Created asset listing Database Systems Textbook.', '2026-05-04 03:00:00'),
('LOG004', 'U005', 'Approve', 'Verification', 'V002', 'Approved textbook asset verification.', '2026-05-05 04:00:00'),
('LOG005', 'U011', 'Create', 'Asset', 'A005', 'Created asset listing LED Study Lamp.', '2026-05-15 08:00:00'),
('LOG006', 'U006', 'Reject', 'Verification', 'V005', 'Rejected unsafe study lamp listing.', '2026-05-16 03:00:00'),
('LOG007', 'U007', 'Submit', 'Mission', 'MS001', 'Submitted Bring Your Own Bottle evidence.', '2026-05-05 01:00:00'),
('LOG008', 'U005', 'Approve', 'Verification', 'V010', 'Approved mission submission MS001.', '2026-05-05 07:00:00'),
('LOG009', 'U009', 'Submit', 'Mission', 'MS010', 'Submitted Donate an Item evidence.', '2026-06-18 04:00:00'),
('LOG010', 'U005', 'Reject', 'Verification', 'V019', 'Rejected mismatched donation evidence.', '2026-06-18 08:00:00'),
('LOG011', 'U003', 'Approve', 'Transaction', 'TR001', 'Approved calculator borrowing request.', '2026-06-03 06:00:00'),
('LOG012', 'U003', 'Update', 'Borrowing', 'B001', 'Confirmed calculator pickup.', '2026-06-04 01:00:00'),
('LOG013', 'U003', 'Update', 'Borrowing', 'B001', 'Completed calculator return inspection.', '2026-06-11 06:00:00'),
('LOG014', 'U004', 'Approve', 'Transaction', 'TR007', 'Approved textbook borrowing request.', '2026-08-20 02:00:00'),
('LOG015', 'U004', 'Update', 'Borrowing', 'B007', 'Confirmed textbook pickup.', '2026-08-20 02:00:00'),
('LOG016', 'U003', 'Update', 'Borrowing', 'B008', 'Marked meal container borrowing overdue.', '2026-08-18 01:00:00'),
('LOG017', 'U007', 'Redeem', 'Reward', 'RR001', 'Redeemed Reusable Water Bottle.', '2026-07-22 02:00:00'),
('LOG018', 'U008', 'Redeem', 'Reward', 'RR002', 'Redeemed Campus Cafe RM5 Voucher.', '2026-06-15 02:00:00'),
('LOG019', 'U010', 'Redeem', 'Reward', 'RR005', 'Redeemed Campus Cafe RM10 Voucher.', '2026-07-20 02:00:00'),
('LOG020', 'U012', 'Redeem', 'Reward', 'RR008', 'Redeemed Eco Shopping Bag.', '2026-07-15 03:00:00'),
('LOG021', 'U001', 'Assign', 'Tier', 'UT001', 'Assigned Sustainability Leader tier to U007.', '2026-08-01 01:00:00'),
('LOG022', 'U001', 'Assign', 'Tier', 'UT004', 'Assigned Eco Legend tier to U010.', '2026-08-01 01:15:00'),
('LOG023', 'U005', 'Assign', 'Badge', 'UAB006', 'Awarded Reward Collector badge to U007.', '2026-08-08 07:10:00'),
('LOG024', 'U006', 'Assign', 'Badge', 'UAB016', 'Awarded Green Legend badge to U010.', '2026-07-12 09:10:00'),
('LOG025', 'U013', 'Create', 'Asset', 'A014', 'Created A3 Cutting Mat listing.', '2026-08-27 03:00:00'),
('LOG026', 'U012', 'Submit', 'Asset', 'A020', 'Submitted HDMI Adapter Set for verification.', '2026-08-24 02:00:00'),
('LOG027', 'U006', 'Reject', 'Verification', 'V009', 'Rejected unsafe electric kettle listing.', '2026-08-19 06:00:00'),
('LOG028', 'U009', 'Create', 'Transaction', 'TR007', 'Requested Database Systems Textbook.', '2026-08-20 00:30:00'),
('LOG029', 'U013', 'Create', 'Transaction', 'TR012', 'Requested Foldable Laptop Table.', '2026-08-27 02:00:00'),
('LOG030', 'U007', 'Create', 'Transaction', 'TR013', 'Requested Bicycle Repair Pump.', '2026-08-27 04:30:00'),
('LOG031', 'U012', 'Create', 'Transaction', 'TR014', 'Requested Resistance Band Set.', '2026-08-26 03:00:00'),
('LOG032', 'U004', 'Reject', 'Transaction', 'TR014', 'Rejected request due to active loan limit.', '2026-08-26 07:00:00'),
('LOG033', 'U011', 'Update', 'Transaction', 'TR015', 'Cancelled Insulated Flask request.', '2026-08-25 06:00:00'),
('LOG034', 'U009', 'Create', 'Verification', 'V019', 'Escalated rejected mission verification through report REP001.', '2026-06-19 01:00:00'),
('LOG035', 'U011', 'Create', 'Verification', 'V028', 'Submitted clearer mission evidence through report REP002.', '2026-08-26 01:00:00'),
('LOG036', 'U001', 'Update', 'Verification', 'V003', 'Resolved charger verification report REP004.', '2026-07-02 02:00:00'),
('LOG037', 'U005', 'Login', 'User', 'U005', 'Auditor logged in successfully.', '2026-08-28 00:10:00'),
('LOG038', 'U003', 'Login', 'User', 'U003', 'Manager logged in successfully.', '2026-08-28 00:15:00'),
('LOG039', 'U001', 'Login', 'User', 'U001', 'Administrator logged in successfully.', '2026-08-28 00:20:00'),
('LOG040', 'U007', 'Login', 'User', 'U007', 'Member logged in successfully.', '2026-08-28 00:25:00'),
('LOG041', 'U001', 'Login', 'User', 'U001', 'Logged in to system', '2026-08-28 13:46:22'),
('LOG042', 'U008', 'Login', 'User', 'U008', 'Logged in to system', '2026-08-28 13:51:59'),
('LOG043', 'U006', 'Login', 'User', 'U006', 'Logged in to system', '2026-08-29 14:54:18'),
('LOG147', 'U006', 'Update', 'Mission', 'M001', 'Updated mission: Bring Your Own Bottle', '2026-08-29 14:55:40'),
('LOG148', 'U006', 'Update', 'Badge', 'AB001', 'Updated achievement badge: Mission Starter', '2026-08-29 15:03:50'),
('LOG149', 'U006', 'Update', 'Badge', 'AB002', 'Updated achievement badge: Mission Explorer', '2026-08-29 15:04:03'),
('LOG150', 'U006', 'Update', 'Badge', 'AB003', 'Updated achievement badge: Mission Master', '2026-08-29 15:04:16'),
('LOG151', 'U006', 'Update', 'Badge', 'AB007', 'Updated achievement badge: Reuse Starter', '2026-08-29 15:04:36'),
('LOG152', 'U006', 'Update', 'Badge', 'AB008', 'Updated achievement badge: Reliable Borrower', '2026-08-29 15:05:12'),
('LOG153', 'U006', 'Update', 'Badge', 'AB008', 'Updated achievement badge: Reliable Borrower', '2026-08-29 15:05:53'),
('LOG154', 'U006', 'Update', 'Badge', 'AB010', 'Updated achievement badge: Community Helper', '2026-08-29 15:06:55'),
('LOG155', 'U006', 'Update', 'Badge', 'AB010', 'Updated achievement badge: Reward Enthusiast', '2026-08-29 15:07:28'),
('LOG156', 'U006', 'Update', 'Badge', 'AB001', 'Updated achievement badge: Mission Starter', '2026-08-29 15:07:49'),
('LOG157', 'U006', 'Update', 'Badge', 'AB001', 'Updated achievement badge: Mission Starter', '2026-08-29 15:08:11'),
('LOG158', 'U006', 'Update', 'Badge', 'AB001', 'Updated achievement badge: Mission Starter', '2026-08-29 15:08:29'),
('LOG159', 'U006', 'Update', 'Reward', 'R003', 'Updated reward: Reusable Water Bottle', '2026-08-29 15:09:20'),
('LOG160', 'U006', 'Update', 'Reward', 'R006', 'Updated reward: APU Stationery Set', '2026-08-29 15:09:56');

-- --------------------------------------------------------

--
-- Table structure for table `tiers`
--

CREATE TABLE `tiers` (
  `tierID` varchar(10) NOT NULL,
  `tierName` varchar(50) NOT NULL,
  `tierDescription` varchar(255) NOT NULL,
  `tierIcon` varchar(255) NOT NULL,
  `minimumEcoPoints` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tiers`
--

INSERT INTO `tiers` (`tierID`, `tierName`, `tierDescription`, `tierIcon`, `minimumEcoPoints`) VALUES
('T001', 'Eco Beginner', 'Starting tier for members beginning their sustainability journey.', 'eco_beginner.png', 0),
('T002', 'Green Enthusiast', 'For members showing regular sustainable participation.', 'green_enthusiast.png', 100),
('T003', 'Eco Champion', 'For active members making a measurable campus impact.', 'eco_champion.png', 300),
('T004', 'Sustainability Leader', 'For members consistently leading by example.', 'sustainability_leader.png', 600),
('T005', 'Eco Legend', 'The highest recognition for exceptional long-term impact.', 'eco_legend.png', 1000);

-- --------------------------------------------------------

--
-- Table structure for table `transactionrequests`
--

CREATE TABLE `transactionrequests` (
  `transactionID` varchar(10) NOT NULL,
  `assetID` varchar(10) NOT NULL,
  `requesterID` varchar(10) NOT NULL,
  `requestDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `requestStatus` enum('Pending','Approved','In Progress','Completed','Cancelled','Rejected','Overdue') NOT NULL DEFAULT 'Pending',
  `managerRemark` varchar(255) DEFAULT NULL,
  `decisionDate` timestamp NULL DEFAULT NULL,
  `requestMessage` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactionrequests`
--

INSERT INTO `transactionrequests` (`transactionID`, `assetID`, `requesterID`, `requestDate`, `requestStatus`, `managerRemark`, `decisionDate`, `requestMessage`) VALUES
('TR001', 'A001', 'U008', '2026-06-03 01:00:00', 'Completed', 'Approved for a one-week coursework loan.', '2026-06-03 06:00:00', 'Needed for a statistics assessment.'),
('TR002', 'A004', 'U009', '2026-06-10 02:00:00', 'Completed', 'Returned in good condition.', '2026-06-10 07:00:00', 'Requested for sports club practice.'),
('TR003', 'A006', 'U010', '2026-06-20 03:00:00', 'Completed', 'Loan completed successfully.', '2026-06-20 08:00:00', 'Needed to record a group presentation.'),
('TR004', 'A008', 'U011', '2026-07-01 01:30:00', 'Completed', 'Repair kit returned with all pieces.', '2026-07-01 04:00:00', 'Needed for a laptop repair.'),
('TR005', 'A010', 'U012', '2026-07-08 06:00:00', 'Completed', 'Approved for society event use.', '2026-07-08 08:00:00', 'Needed for a sustainability booth.'),
('TR006', 'A013', 'U007', '2026-07-15 02:00:00', 'Completed', 'Completed without issue.', '2026-07-15 05:00:00', 'Requested for a wellness workshop.'),
('TR007', 'A002', 'U009', '2026-08-20 00:30:00', 'In Progress', 'Collected and currently on loan.', '2026-08-20 02:00:00', 'Needed for database revision.'),
('TR008', 'A009', 'U011', '2026-08-10 01:00:00', 'Overdue', 'Return reminder sent to borrower.', '2026-08-10 03:00:00', 'Requested for a zero-waste event.'),
('TR009', 'A017', 'U013', '2026-08-15 02:00:00', 'In Progress', 'Microphone collected by borrower.', '2026-08-15 04:00:00', 'Needed for a student podcast recording.'),
('TR010', 'A003', 'U010', '2026-08-26 04:00:00', 'Approved', 'Reserved for pickup on 29 August.', '2026-08-26 08:00:00', 'My laptop charger stopped working.'),
('TR011', 'A012', 'U008', '2026-08-27 01:00:00', 'Approved', 'Reserved for presentation day.', '2026-08-27 06:00:00', 'Needed for my final-year presentation.'),
('TR012', 'A016', 'U013', '2026-08-27 02:00:00', 'Pending', NULL, NULL, 'I need a temporary study table.'),
('TR013', 'A019', 'U007', '2026-08-27 04:30:00', 'Pending', NULL, NULL, 'My bicycle tyre needs inflation.'),
('TR014', 'A021', 'U012', '2026-08-26 03:00:00', 'Rejected', 'Member already had the maximum number of active loans.', '2026-08-26 07:00:00', 'Requested for weekend exercise.'),
('TR015', 'A022', 'U011', '2026-08-25 05:00:00', 'Cancelled', 'Cancelled by requester before approval.', NULL, 'Needed for an outdoor club trip.'),
('TR016', 'A015', 'U010', '2026-05-20 02:00:00', 'Completed', 'Notes returned on time.', '2026-05-20 05:00:00', 'Needed for examination revision.'),
('TR017', 'A018', 'U008', '2026-07-22 01:00:00', 'Completed', 'Event supplies returned clean.', '2026-07-22 03:00:00', 'Needed for a student club fair.'),
('TR018', 'A011', 'U009', '2026-08-28 00:00:00', 'Pending', NULL, NULL, 'Needed for an internship interview.');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `userID` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Member','Auditor','Manager','Admin') NOT NULL DEFAULT 'Member',
  `status` enum('Active','Suspended','Deleted') NOT NULL DEFAULT 'Active',
  `ecopoint` int(11) NOT NULL DEFAULT 0,
  `dateCreated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`userID`, `name`, `email`, `password`, `role`, `status`, `ecopoint`, `dateCreated`) VALUES
('U001', 'Alicia Tan', 'admin@apu.edu.my', 'P@ssw0rd!23', 'Admin', 'Active', 0, '2026-01-05 01:00:00'),
('U002', 'Daniel Lee', 'admin2@apu.edu.my', 'P@ssw0rd!23', 'Admin', 'Active', 0, '2026-02-03 02:00:00'),
('U003', 'Farah Ahmad', 'manager@apu.edu.my', 'P@ssw0rd!23', 'Manager', 'Active', 0, '2026-01-08 01:30:00'),
('U004', 'Marcus Wong', 'manager2@apu.edu.my', 'P@ssw0rd!23', 'Manager', 'Active', 0, '2026-03-02 03:00:00'),
('U005', 'Nur Aisyah', 'auditor@apu.edu.my', 'P@ssw0rd!23', 'Auditor', 'Active', 0, '2026-01-10 00:45:00'),
('U006', 'Jason Lim', 'auditor2@apu.edu.my', '$2y$10$FdFCbtIFFev1a9RwlXhpo.DQ.xt/akkvNhc4OXB.9h41rxRDEx3hW', 'Auditor', 'Active', 0, '2026-04-14 06:00:00'),
('U007', 'Amir Hakim', 'amir@apu.edu.my', 'P@ssw0rd!23', 'Member', 'Active', 720, '2026-02-01 01:15:00'),
('U008', 'Mei Xin', 'meixin@apu.edu.my', 'P@ssw0rd!23', 'Member', 'Active', 480, '2026-02-12 02:20:00'),
('U009', 'Raj Kumar', 'raj@apu.edu.my', 'P@ssw0rd!23', 'Member', 'Active', 315, '2026-03-05 04:00:00'),
('U010', 'Siti Hawa', 'sitihawa@apu.edu.my', 'P@ssw0rd!23', 'Member', 'Active', 1080, '2026-03-20 07:10:00'),
('U011', 'Ethan Goh', 'ethan@apu.edu.my', 'P@ssw0rd!23', 'Member', 'Active', 165, '2026-04-09 03:40:00'),
('U012', 'Priya Nair', 'priya@apu.edu.my', 'P@ssw0rd!23', 'Member', 'Active', 590, '2026-05-01 05:20:00'),
('U013', 'Adam Chen', 'adam@apu.edu.my', 'P@ssw0rd!23', 'Member', 'Active', 90, '2026-05-18 08:00:00'),
('U014', 'Nadia Yusuf', 'nadia@apu.edu.my', 'P@ssw0rd!23', 'Member', 'Suspended', 230, '2026-06-11 01:50:00'),
('U015', 'Kelvin Ong', 'kelvin@apu.edu.my', 'P@ssw0rd!23', 'Member', 'Deleted', 0, '2026-06-30 09:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `user achievement badge`
--

CREATE TABLE `user achievement badge` (
  `userBadgeID` varchar(10) NOT NULL,
  `userID` varchar(10) NOT NULL,
  `achieveBadgeID` varchar(10) NOT NULL,
  `awardedDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user achievement badge`
--

INSERT INTO `user achievement badge` (`userBadgeID`, `userID`, `achieveBadgeID`, `awardedDate`) VALUES
('UAB001', 'U007', 'AB001', '2026-05-05 07:10:00'),
('UAB002', 'U007', 'AB002', '2026-07-20 05:10:00'),
('UAB003', 'U007', 'AB004', '2026-05-18 08:10:00'),
('UAB004', 'U007', 'AB005', '2026-07-20 05:10:00'),
('UAB005', 'U007', 'AB007', '2026-07-30 03:10:00'),
('UAB006', 'U007', 'AB009', '2026-08-08 07:10:00'),
('UAB007', 'U008', 'AB001', '2026-05-07 07:10:00'),
('UAB008', 'U008', 'AB004', '2026-06-09 02:10:00'),
('UAB009', 'U008', 'AB009', '2026-08-16 02:10:00'),
('UAB010', 'U009', 'AB001', '2026-06-01 08:10:00'),
('UAB011', 'U009', 'AB004', '2026-06-01 08:10:00'),
('UAB012', 'U010', 'AB001', '2026-04-20 06:10:00'),
('UAB013', 'U010', 'AB002', '2026-06-21 06:10:00'),
('UAB014', 'U010', 'AB004', '2026-05-02 08:10:00'),
('UAB015', 'U010', 'AB005', '2026-06-21 06:10:00'),
('UAB016', 'U010', 'AB006', '2026-07-12 09:10:00'),
('UAB017', 'U011', 'AB001', '2026-06-09 07:10:00'),
('UAB018', 'U011', 'AB004', '2026-07-05 04:10:00'),
('UAB019', 'U012', 'AB001', '2026-05-11 07:10:00'),
('UAB020', 'U012', 'AB004', '2026-06-13 09:10:00'),
('UAB021', 'U012', 'AB005', '2026-08-12 05:10:00'),
('UAB022', 'U013', 'AB001', '2026-07-15 09:10:00');

-- --------------------------------------------------------

--
-- Table structure for table `usertier`
--

CREATE TABLE `usertier` (
  `userTierID` varchar(10) NOT NULL,
  `userID` varchar(10) NOT NULL,
  `tierID` varchar(10) NOT NULL,
  `assignedDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usertier`
--

INSERT INTO `usertier` (`userTierID`, `userID`, `tierID`, `assignedDate`) VALUES
('UT001', 'U007', 'T004', '2026-08-01 01:00:00'),
('UT002', 'U008', 'T003', '2026-08-01 01:05:00'),
('UT003', 'U009', 'T003', '2026-08-01 01:10:00'),
('UT004', 'U010', 'T005', '2026-08-01 01:15:00'),
('UT005', 'U011', 'T002', '2026-08-01 01:20:00'),
('UT006', 'U012', 'T003', '2026-08-01 01:25:00'),
('UT007', 'U013', 'T001', '2026-08-01 01:30:00'),
('UT008', 'U014', 'T002', '2026-08-01 01:35:00'),
('UT009', 'U015', 'T001', '2026-08-01 01:40:00');

-- --------------------------------------------------------

--
-- Table structure for table `verificationrecords`
--

CREATE TABLE `verificationrecords` (
  `verificationID` varchar(10) NOT NULL,
  `userID` varchar(10) NOT NULL,
  `verifiedBy` varchar(10) DEFAULT NULL,
  `targetID` varchar(10) NOT NULL,
  `targetType` varchar(50) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `verifiedDate` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verificationrecords`
--

INSERT INTO `verificationrecords` (`verificationID`, `userID`, `verifiedBy`, `targetID`, `targetType`, `status`, `remarks`, `verifiedDate`) VALUES
('V001', 'U007', 'U005', 'A001', 'Asset', 'Approved', 'Item tested and listing details confirmed.', '2026-05-03 06:00:00'),
('V002', 'U008', 'U005', 'A002', 'Asset', 'Approved', 'Book condition matches the listing.', '2026-05-05 04:00:00'),
('V003', 'U009', 'U006', 'A003', 'Asset', 'Approved', 'Charger passed a basic functionality check.', '2026-05-09 02:00:00'),
('V004', 'U010', 'U005', 'A004', 'Asset', 'Approved', 'Both rackets are safe and usable.', '2026-05-13 07:00:00'),
('V005', 'U011', 'U006', 'A005', 'Asset', 'Rejected', 'Power cable damage makes this unsafe to circulate.', '2026-05-16 03:00:00'),
('V006', 'U012', 'U005', 'A006', 'Asset', 'Approved', 'Tripod and remote are functional.', '2026-05-21 04:00:00'),
('V007', 'U013', NULL, 'A007', 'Asset', 'Pending', NULL, NULL),
('V008', 'U012', NULL, 'A020', 'Asset', 'Pending', NULL, NULL),
('V009', 'U009', 'U006', 'A024', 'Asset', 'Rejected', 'Loose connector poses an electrical safety risk.', '2026-08-19 06:00:00'),
('V010', 'U007', 'U005', 'MS001', 'MissionSubmission', 'Approved', 'Reusable bottle evidence is clear.', '2026-05-05 07:00:00'),
('V011', 'U007', 'U006', 'MS002', 'MissionSubmission', 'Approved', 'Approved e-waste point is visible.', '2026-05-18 08:00:00'),
('V012', 'U007', 'U005', 'MS003', 'MissionSubmission', 'Approved', 'Public transport evidence verified.', '2026-06-02 06:00:00'),
('V013', 'U007', 'U006', 'MS004', 'MissionSubmission', 'Approved', 'Published sustainability tip verified.', '2026-07-10 09:00:00'),
('V014', 'U008', 'U005', 'MS005', 'MissionSubmission', 'Approved', 'Bottle use at refill station verified.', '2026-05-07 07:00:00'),
('V015', 'U008', 'U006', 'MS006', 'MissionSubmission', 'Approved', 'Reusable bag is clearly in use.', '2026-05-22 09:00:00'),
('V016', 'U008', 'U005', 'MS007', 'MissionSubmission', 'Approved', 'Digital study materials verified.', '2026-06-09 02:00:00'),
('V017', 'U008', NULL, 'MS008', 'MissionSubmission', 'Pending', NULL, NULL),
('V018', 'U009', 'U006', 'MS009', 'MissionSubmission', 'Approved', 'Cleanup evidence is complete.', '2026-06-01 08:00:00'),
('V019', 'U009', 'U005', 'MS010', 'MissionSubmission', 'Rejected', 'Donation receipt does not match the submitted item.', '2026-06-18 08:00:00'),
('V020', 'U009', NULL, 'MS011', 'MissionSubmission', 'Pending', NULL, NULL),
('V021', 'U010', 'U005', 'MS012', 'MissionSubmission', 'Approved', 'Reusable bottle evidence verified.', '2026-04-20 06:00:00'),
('V022', 'U010', 'U006', 'MS013', 'MissionSubmission', 'Approved', 'E-waste drop-off evidence verified.', '2026-05-02 08:00:00'),
('V023', 'U010', 'U005', 'MS014', 'MissionSubmission', 'Approved', 'Campus cleanup activity verified.', '2026-05-16 06:00:00'),
('V024', 'U010', 'U006', 'MS015', 'MissionSubmission', 'Approved', 'Donation confirmation verified.', '2026-06-04 09:00:00'),
('V025', 'U010', 'U005', 'MS016', 'MissionSubmission', 'Approved', 'Green transport trip verified.', '2026-06-21 06:00:00'),
('V026', 'U010', 'U006', 'MS017', 'MissionSubmission', 'Approved', 'Community tip publication verified.', '2026-07-12 09:00:00'),
('V027', 'U011', 'U005', 'MS018', 'MissionSubmission', 'Approved', 'Reusable bag evidence verified.', '2026-06-09 07:00:00'),
('V028', 'U011', 'U006', 'MS019', 'MissionSubmission', 'Rejected', 'Container is not visible in the submitted image.', '2026-08-25 08:00:00'),
('V029', 'U012', 'U005', 'MS020', 'MissionSubmission', 'Approved', 'Bottle evidence verified.', '2026-05-11 07:00:00'),
('V030', 'U012', 'U006', 'MS021', 'MissionSubmission', 'Approved', 'Paperless study evidence verified.', '2026-06-13 09:00:00'),
('V031', 'U012', 'U005', 'MS022', 'MissionSubmission', 'Approved', 'Food waste evidence verified.', '2026-07-06 08:00:00'),
('V032', 'U013', 'U006', 'MS023', 'MissionSubmission', 'Approved', 'Reusable bag use verified.', '2026-07-15 09:00:00'),
('V033', 'U013', NULL, 'MS024', 'MissionSubmission', 'Pending', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievementbadges`
--
ALTER TABLE `achievementbadges`
  ADD PRIMARY KEY (`achieveBadgeID`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`assetID`),
  ADD KEY `fk_assets_owner` (`ownerID`),
  ADD KEY `fk_assets_category` (`categoryID`);

--
-- Indexes for table `assetstatushistory`
--
ALTER TABLE `assetstatushistory`
  ADD PRIMARY KEY (`historyID`),
  ADD KEY `fk_ash_asset` (`assetID`),
  ADD KEY `fk_ash_user` (`updatedBy`);

--
-- Indexes for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD PRIMARY KEY (`borrowID`),
  ADD KEY `fk_borr_asset` (`assetID`),
  ADD KEY `fk_borr_borrower` (`borrowerID`),
  ADD KEY `fk_borr_transaction` (`transactionID`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`categoryID`),
  ADD UNIQUE KEY `unique_category_name` (`categoryName`);

--
-- Indexes for table `ecopointstransaction`
--
ALTER TABLE `ecopointstransaction`
  ADD PRIMARY KEY (`transactionID`),
  ADD KEY `fk_ept_user` (`userID`);

--
-- Indexes for table `missions`
--
ALTER TABLE `missions`
  ADD PRIMARY KEY (`missionID`);

--
-- Indexes for table `missionsubmission`
--
ALTER TABLE `missionsubmission`
  ADD PRIMARY KEY (`submissionID`),
  ADD KEY `fk_msub_user` (`userID`),
  ADD KEY `fk_msub_mission` (`missionID`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`notificationID`),
  ADD KEY `fk_notif_user` (`userID`);

--
-- Indexes for table `report`
--
ALTER TABLE `report`
  ADD PRIMARY KEY (`reportID`),
  ADD KEY `fk_rep_reporter` (`reporterID`),
  ADD KEY `fk_rep_verification` (`verificationID`);

--
-- Indexes for table `rewardredemption`
--
ALTER TABLE `rewardredemption`
  ADD PRIMARY KEY (`redemptionID`),
  ADD KEY `fk_rred_user` (`userID`),
  ADD KEY `fk_rred_reward` (`rewardID`);

--
-- Indexes for table `rewards`
--
ALTER TABLE `rewards`
  ADD PRIMARY KEY (`rewardID`);

--
-- Indexes for table `systemlog`
--
ALTER TABLE `systemlog`
  ADD PRIMARY KEY (`logID`),
  ADD KEY `fk_systemlog_user` (`userID`);

--
-- Indexes for table `tiers`
--
ALTER TABLE `tiers`
  ADD PRIMARY KEY (`tierID`);

--
-- Indexes for table `transactionrequests`
--
ALTER TABLE `transactionrequests`
  ADD PRIMARY KEY (`transactionID`),
  ADD KEY `fk_treq_asset` (`assetID`),
  ADD KEY `fk_treq_requester` (`requesterID`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`userID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user achievement badge`
--
ALTER TABLE `user achievement badge`
  ADD PRIMARY KEY (`userBadgeID`),
  ADD KEY `fk_uab_user` (`userID`),
  ADD KEY `fk_uab_badge` (`achieveBadgeID`);

--
-- Indexes for table `usertier`
--
ALTER TABLE `usertier`
  ADD PRIMARY KEY (`userTierID`),
  ADD KEY `fk_usertier_user` (`userID`),
  ADD KEY `fk_usertier_tier` (`tierID`);

--
-- Indexes for table `verificationrecords`
--
ALTER TABLE `verificationrecords`
  ADD PRIMARY KEY (`verificationID`),
  ADD KEY `fk_vrec_user` (`userID`),
  ADD KEY `fk_vrec_auditor` (`verifiedBy`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `fk_assets_category` FOREIGN KEY (`categoryID`) REFERENCES `categories` (`categoryID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assets_owner` FOREIGN KEY (`ownerID`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `assetstatushistory`
--
ALTER TABLE `assetstatushistory`
  ADD CONSTRAINT `fk_ash_asset` FOREIGN KEY (`assetID`) REFERENCES `assets` (`assetID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ash_user` FOREIGN KEY (`updatedBy`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD CONSTRAINT `fk_borr_asset` FOREIGN KEY (`assetID`) REFERENCES `assets` (`assetID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_borr_borrower` FOREIGN KEY (`borrowerID`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_borr_transaction` FOREIGN KEY (`transactionID`) REFERENCES `transactionrequests` (`transactionID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ecopointstransaction`
--
ALTER TABLE `ecopointstransaction`
  ADD CONSTRAINT `fk_ept_user` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `missionsubmission`
--
ALTER TABLE `missionsubmission`
  ADD CONSTRAINT `fk_msub_mission` FOREIGN KEY (`missionID`) REFERENCES `missions` (`missionID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_msub_user` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `report`
--
ALTER TABLE `report`
  ADD CONSTRAINT `fk_rep_reporter` FOREIGN KEY (`reporterID`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rep_verification` FOREIGN KEY (`verificationID`) REFERENCES `verificationrecords` (`verificationID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `rewardredemption`
--
ALTER TABLE `rewardredemption`
  ADD CONSTRAINT `fk_rred_reward` FOREIGN KEY (`rewardID`) REFERENCES `rewards` (`rewardID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rred_user` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `systemlog`
--
ALTER TABLE `systemlog`
  ADD CONSTRAINT `fk_systemlog_user` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`) ON UPDATE CASCADE;

--
-- Constraints for table `transactionrequests`
--
ALTER TABLE `transactionrequests`
  ADD CONSTRAINT `fk_treq_asset` FOREIGN KEY (`assetID`) REFERENCES `assets` (`assetID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_treq_requester` FOREIGN KEY (`requesterID`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user achievement badge`
--
ALTER TABLE `user achievement badge`
  ADD CONSTRAINT `fk_uab_badge` FOREIGN KEY (`achieveBadgeID`) REFERENCES `achievementbadges` (`achieveBadgeID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uab_user` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `usertier`
--
ALTER TABLE `usertier`
  ADD CONSTRAINT `fk_usertier_tier` FOREIGN KEY (`tierID`) REFERENCES `tiers` (`tierID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usertier_user` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `verificationrecords`
--
ALTER TABLE `verificationrecords`
  ADD CONSTRAINT `fk_vrec_auditor` FOREIGN KEY (`verifiedBy`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vrec_user` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
