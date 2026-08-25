-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 25, 2026 at 11:34 AM
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
('AB001', 'First Recycling Mission', 'Awarded for completing your first green campus mission', 'Complete 1 mission', 'badge_first_mission.png'),
('AB002', 'Master Trader', 'Awarded for successfully exchanging 5 items', 'Complete 5 exchanges', 'badge_master_trader.png'),
('AB003', 'Eco Pioneer', 'Earned 500 total EcoPoints', 'Reach 500 EcoPoints', 'badge_pioneer.png');

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
('A001', 'U004', 'C001', 'Dell Wireless Mouse', 'Compact wireless mouse for daily use.', 'Good', 'uploads/assets/mouse.jpg', 'Available', 'Removed', '2026-07-27 07:01:16', '2026-08-11 15:23:23', 'Approved'),
('A002', 'U005', 'C002', 'Java Programming Textbook', '8th Edition Java reference guide in good condition', 'Good', 'uploads/assets/book.jpg', 'Available', 'Published', '2026-07-27 07:01:16', '2026-08-13 15:48:20', 'Approved'),
('A003', 'U004', 'C001', 'USB-C Fast Charger', 'Original 65W USB-C power brick.', 'Good', 'uploads/assets/charger.jpg', 'Unavailable', 'Removed', '2026-07-27 07:01:16', '2026-08-11 15:23:23', 'Pending'),
('A004', 'U002', 'C001', 'Portable HDMI adapter', 'Compact HDMI adapter for presentations and daily use.', 'Good', 'assets/managerfile/ast004_1786360473.jpg', 'Available', 'Removed', '2026-08-10 11:12:08', '2026-08-11 15:23:23', 'Pending'),
('A005', 'U004', 'C001', 'Wireless Keyboard', 'Compact wireless keyboard suitable for study and office use.', 'Good', 'assets/managerfile/mouse.jpg', 'Unavailable', 'Removed', '2026-08-10 15:26:22', '2026-08-11 15:23:23', 'Pending'),
('A006', 'U005', 'C002', 'Data Structures Textbook', 'Reference textbook covering data structures and algorithms.', 'Like New', 'assets/managerfile/book.jpg', 'Unavailable', 'Pending Review', '2026-08-10 15:26:22', '2026-08-25 05:57:26', 'Pending'),
('A007', 'U004', 'C001', 'USB-C Charging Cable', 'One metre USB-C charging cable in good working condition.', 'Good', 'assets/managerfile/charger.jpg', 'Unavailable', 'Removed', '2026-08-10 15:26:22', '2026-08-14 10:54:59', 'Pending'),
('A008', 'U005', 'C002', 'Scientific Calculator', 'Scientific calculator suitable for university mathematics subjects.', 'Good', 'assets/managerfile/mouse.jpg', 'Unavailable', 'Ready for Verification', '2026-08-10 15:26:22', '2026-08-13 13:00:48', 'Pending'),
('A960', 'U004', 'C001', 'Wireless Mouse', 'Compact wireless mouse suitable for study and office use.', 'Good', NULL, 'Unavailable', 'Ready for Verification', '2026-08-19 15:16:50', '2026-08-19 15:18:22', 'Approved'),
('A961', 'U004', 'C001', 'Laptop Cooling Pad', 'Portable cooling pad for laptops with adjustable stand.', 'Like New', NULL, 'Unavailable', 'Ready for Verification', '2026-08-19 15:16:50', '2026-08-19 15:22:44', 'Pending'),
('A962', 'U004', 'C001', 'USB Webcam', 'USB webcam suitable for online classes and video meetings.', 'Good', NULL, 'Unavailable', 'Ready for Verification', '2026-08-19 15:16:50', '2026-08-19 15:22:48', 'Pending'),
('A963', 'U004', 'C001', 'Portable Bluetooth Speaker', 'Small portable speaker suitable for events and group activities.', 'Fair', NULL, 'Unavailable', 'Ready for Verification', '2026-08-19 15:16:50', '2026-08-19 15:22:51', 'Pending'),
('A964', 'U004', 'C001', 'Scientific Calculator', 'Scientific calculator suitable for mathematics and engineering subjects.', 'Like New', NULL, 'Unavailable', 'Draft', '2026-08-19 15:16:50', '2026-08-19 15:19:34', 'Pending'),
('A965', 'U004', 'C001', 'USB-C Adapter', 'Multi-port USB-C adapter with USB and HDMI connections.', 'Good', NULL, 'Unavailable', 'Pending Review', '2026-08-19 15:16:50', NULL, 'Pending'),
('A966', 'U004', 'C001', 'Portable Power Bank', 'Rechargeable portable power bank for mobile devices.', 'Fair', NULL, 'Unavailable', 'Pending Review', '2026-08-19 15:16:50', NULL, 'Pending'),
('A967', 'U004', 'C001', 'External Keyboard', 'Full-size external keyboard suitable for computer laboratory or study use.', 'Good', NULL, 'Unavailable', 'Pending Review', '2026-08-19 15:16:50', NULL, 'Pending'),
('A970', 'U004', 'C001', 'Wireless Presentation Mouse', 'Compact wireless mouse suitable for presentations, study sessions and daily campus use.', 'Good', 'uploads/assets/mouse.jpg', 'Unavailable', 'Pending Review', '2026-08-24 08:19:17', NULL, 'Pending'),
('A971', 'U005', 'C002', 'Operating Systems Reference Book', 'Well-kept reference book for operating systems concepts and coursework revision.', 'Like New', 'uploads/assets/book.jpg', 'Unavailable', 'Ready for Verification', '2026-08-23 08:19:17', '2026-08-24 08:19:17', 'Pending'),
('A972', 'U005', 'C001', 'Wireless Optical Mouse', 'Reliable wireless optical mouse for laptop and desktop use.', 'Good', 'uploads/assets/mouse.jpg', 'Reserved', 'Published', '2026-08-13 08:19:17', '2026-08-25 08:20:19', 'Approved'),
('A973', 'U004', 'C001', '65W USB-C Charger', 'USB-C fast charger suitable for compatible laptops, tablets and mobile devices.', 'Like New', 'uploads/assets/charger.jpg', 'Reserved', 'Published', '2026-08-10 08:19:17', '2026-08-25 08:20:33', 'Approved'),
('A974', 'U005', 'C002', 'Database Systems Textbook', 'Reference textbook covering database design, SQL and relational database concepts.', 'Good', 'uploads/assets/book.jpg', 'Available', 'Published', '2026-08-07 08:19:17', '2026-08-14 08:19:17', 'Approved'),
('A975', 'U005', 'C001', 'Portable USB-C Charger', 'Portable USB-C charger prepared and reserved for an approved campus borrowing.', 'Good', 'uploads/assets/charger.jpg', 'Reserved', 'Published', '2026-08-05 08:19:17', '2026-08-25 08:19:17', 'Approved'),
('A976', 'U004', 'C002', 'Software Engineering Textbook', 'Software engineering reference textbook in excellent condition for coursework use.', 'Like New', 'uploads/assets/book.jpg', 'Reserved', 'Published', '2026-08-04 08:19:17', '2026-08-25 08:19:17', 'Approved'),
('A977', 'U004', 'C001', 'USB-C Charging Cable', 'Durable USB-C charging cable available for short-term campus borrowing.', 'Good', 'uploads/assets/charger.jpg', 'Available', 'Published', '2026-08-09 08:19:17', '2026-08-16 08:19:17', 'Approved'),
('A978', 'U004', 'C001', 'Bluetooth Wireless Mouse', 'Bluetooth mouse currently on loan to a community member.', 'Good', 'uploads/assets/mouse.jpg', 'Borrowed', 'Published', '2026-07-31 08:19:17', '2026-08-23 08:19:17', 'Approved'),
('A979', 'U005', 'C002', 'Data Structures Reference Book', 'Reference book currently borrowed and awaiting an overdue return.', 'Good', 'uploads/assets/book.jpg', 'Borrowed', 'Published', '2026-07-26 08:19:17', '2026-08-17 08:19:17', 'Approved'),
('A980', 'U004', 'C002', 'Programming Fundamentals Textbook', 'Introductory programming textbook previously borrowed and returned successfully.', 'Like New', 'uploads/assets/book.jpg', 'Available', 'Published', '2026-07-21 08:19:17', '2026-08-20 08:19:17', 'Approved'),
('A981', 'U005', 'C001', 'USB-C Fast Charger', 'Compact fast charger with a completed historical borrowing request.', 'Good', 'assets/managerfile/a981_1787648187.jpg', 'Available', 'Published', '2026-07-28 08:19:17', '2026-08-25 08:56:27', 'Approved'),
('A982', 'U004', 'C002', 'Computer Networks Textbook', 'Computer networking reference textbook with a cancelled reservation history.', 'Good', 'uploads/assets/book.jpg', 'Available', 'Published', '2026-07-24 08:19:17', '2026-08-18 08:19:17', 'Approved'),
('A983', 'U005', 'C001', 'Sealed USB-C Cable', 'New reusable cable with no active reservation; suitable for the soft-removal demonstration.', 'New', 'uploads/assets/charger.jpg', 'Available', 'Published', '2026-08-13 08:41:37', NULL, 'Approved'),
('A984', 'U004', 'C889', 'Legacy VGA Adapter', 'Historical accessory rejected because its condition is unsuitable for circulation.', 'Poor', 'assets/icons/category-electronics.png', 'Unavailable', 'Rejected', '2026-07-31 08:41:37', '2026-08-05 08:41:37', 'Rejected'),
('A985', 'U005', 'C003', 'Portable Whiteboard', 'Manager-readable listing in the Approved stage for status-filter and report screenshots.', 'Fair', 'assets/icons/category-studyequipment.png', 'Unavailable', 'Approved', '2026-08-17 08:41:37', '2026-08-20 08:41:37', 'Approved'),
('A986', 'U004', 'C001', 'USB-C Multiport Hub', 'Recent Community Member submission waiting for Resource Manager review.', 'Good', 'assets/managerfile/a981_1787648187.jpg', 'Unavailable', 'Draft', '2026-08-25 08:40:37', '2026-08-25 09:26:52', 'Pending'),
('A987', 'U005', 'C002', 'Scientific Calculator', 'Second recent submission available for review, correction or verification workflow screenshots.', 'Like New', 'assets/managerfile/scientificcalc.jpg', 'Unavailable', 'Ready for Verification', '2026-08-25 08:38:37', '2026-08-25 09:33:13', 'Pending'),
('A988', 'U005', 'C001', 'HDMI Presentation Adapter', 'Published and verified asset linked to a valid pending request with pickup today.', 'Good', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-08-16 08:41:37', NULL, 'Approved'),
('A989', 'U004', 'C002', 'Algorithms Reference Book', 'Published and verified asset linked to a pending request with pickup tomorrow.', 'Like New', 'uploads/assets/book.jpg', 'Available', 'Published', '2026-08-15 08:41:37', NULL, 'Approved'),
('A990', 'U005', 'C001', 'USB Webcam', 'Published and verified asset linked to a pending request whose pickup date has passed.', 'Good', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-08-14 08:41:37', NULL, 'Approved'),
('A991', 'U005', 'C887', 'Portable Projector', 'Approved protected reservation waiting for physical pickup confirmation.', 'Good', 'assets/icons/category-electronics.png', 'Reserved', 'Published', '2026-08-10 08:41:37', '2026-08-25 08:41:37', 'Approved'),
('A992', 'U005', 'C003', 'Adjustable Laptop Stand', 'Approved future reservation outside the seven-day protection window, so the asset remains available.', 'Like New', 'assets/icons/category-furniture.png', 'Available', 'Published', '2026-08-09 08:41:37', NULL, 'Approved'),
('A993', 'U005', 'C002', 'Graphic Calculator', 'Currently borrowed asset that is still within its expected return period.', 'Good', 'assets/icons/category-studyequipment.png', 'Borrowed', 'Published', '2026-08-07 08:41:37', '2026-08-25 08:41:37', 'Approved'),
('A994', 'U005', 'C002', 'Database Revision Book', 'Currently borrowed asset with a date-derived overdue return.', 'Fair', 'uploads/assets/book.jpg', 'Borrowed', 'Published', '2026-08-05 08:41:37', '2026-08-25 08:41:37', 'Approved'),
('A995', 'U004', 'C887', 'Shared Camera Tripod', 'Current borrowing with another approved reservation already inside its seven-day protection period.', 'Good', 'assets/icons/category-others.png', 'Borrowed', 'Published', '2026-08-03 08:41:37', '2026-08-25 08:41:37', 'Approved'),
('A996', 'U004', 'C889', 'Retired Mouse Pad', 'Soft-removed historical listing retained for audit and inactive-category linkage.', 'Fair', 'assets/icons/category-others.png', 'Unavailable', 'Removed', '2026-07-21 08:41:37', '2026-08-10 08:41:37', 'Approved');

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
('H001', 'A002', 'U002', 'Available', 'Borrowed', 'Approved borrow request TR001', '2026-07-27 07:01:17'),
('H002', 'A007', 'U002', 'Unavailable', 'Available', 'Availability updated during asset review.', '2026-08-11 12:36:39'),
('H003', 'A007', 'U002', 'Available', 'Unavailable', 'Availability updated during asset review.', '2026-08-11 12:53:57'),
('H896', 'A002', 'U002', 'Borrowed', 'Available', 'Return inspection completed for TR001', '2026-08-13 15:48:20'),
('H897', 'A972', 'U002', 'Available', 'Reserved', '7-day reservation protection activated for request TR970', '2026-08-25 08:20:19'),
('H898', 'A973', 'U002', 'Available', 'Reserved', '7-day reservation protection activated for request TR971', '2026-08-25 08:20:33'),
('H899', 'A991', 'U002', 'Available', 'Reserved', '7-day reservation protection activated for request TR984.', '2026-08-25 08:33:37'),
('H900', 'A993', 'U002', 'Reserved', 'Borrowed', 'Pickup confirmed for transaction TR986.', '2026-08-22 08:41:37'),
('H901', 'A994', 'U002', 'Reserved', 'Borrowed', 'Pickup confirmed for transaction TR987.', '2026-08-15 08:41:37'),
('H902', 'A995', 'U002', 'Reserved', 'Borrowed', 'Pickup confirmed for transaction TR988.', '2026-08-19 08:41:37');

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
('B001', 'A002', 'U004', 'TR001', '2026-07-27 07:01:17', '2026-08-10 04:00:00', '2026-08-13 23:48:20', 'Completed'),
('B970', 'A972', 'U004', 'TR970', '2026-08-25 07:00:00', '2026-08-28 00:00:00', NULL, 'Approved'),
('B971', 'A973', 'U005', 'TR971', '2026-08-26 02:00:00', '2026-08-29 00:00:00', NULL, 'Approved'),
('B972', 'A974', 'U004', 'TR972', '2026-08-29 02:00:00', '2026-09-02 00:00:00', NULL, 'Pending'),
('B973', 'A975', 'U004', 'TR973', '2026-08-25 03:00:00', '2026-08-30 00:00:00', NULL, 'Approved'),
('B974', 'A976', 'U005', 'TR974', '2026-08-28 02:00:00', '2026-09-02 00:00:00', NULL, 'Approved'),
('B975', 'A977', 'U005', 'TR975', '2026-09-04 02:00:00', '2026-09-08 00:00:00', NULL, 'Approved'),
('B976', 'A978', 'U005', 'TR976', '2026-08-23 02:00:00', '2026-08-28 00:00:00', NULL, 'Borrowed'),
('B977', 'A979', 'U006', 'TR977', '2026-08-17 02:00:00', '2026-08-24 00:00:00', NULL, 'Overdue'),
('B978', 'A980', 'U006', 'TR978', '2026-08-15 02:00:00', '2026-08-21 00:00:00', '2026-08-20 15:00:00', 'Completed'),
('B979', 'A981', 'U004', 'TR979', '2026-08-27 02:00:00', '2026-08-31 00:00:00', NULL, 'Cancelled'),
('B980', 'A982', 'U005', 'TR980', '2026-08-31 02:00:00', '2026-09-04 00:00:00', NULL, 'Cancelled'),
('B981', 'A988', 'U004', 'TR981', '2026-08-25 07:00:00', '2026-09-01 00:00:00', NULL, 'Pending'),
('B982', 'A989', 'U005', 'TR982', '2026-08-26 02:00:00', '2026-09-02 00:00:00', NULL, 'Pending'),
('B983', 'A990', 'U004', 'TR983', '2026-08-23 16:00:00', '2026-08-30 00:00:00', NULL, 'Pending'),
('B984', 'A991', 'U004', 'TR984', '2026-08-25 06:00:00', '2026-08-29 00:00:00', NULL, 'Approved'),
('B985', 'A992', 'U990', 'TR985', '2026-09-05 16:00:00', '2026-09-13 00:00:00', NULL, 'Approved'),
('B986', 'A993', 'U004', 'TR986', '2026-08-22 16:00:00', '2026-08-28 00:00:00', NULL, 'Borrowed'),
('B987', 'A994', 'U990', 'TR987', '2026-08-14 16:00:00', '2026-08-23 00:00:00', NULL, 'Borrowed'),
('B988', 'A995', 'U991', 'TR988', '2026-08-19 16:00:00', '2026-08-26 00:00:00', NULL, 'Borrowed'),
('B989', 'A995', 'U991', 'TR989', '2026-08-27 16:00:00', '2026-09-04 00:00:00', NULL, 'Approved');

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
('C001', 'Electronics', 'Laptops, chargers, phones, and hardware accessories', 'assets/icons/category-electronics.png', 'Active', '2026-07-27 07:01:16'),
('C002', 'Books & Stationery', 'Textbooks, revision guides, and note pads', 'assets/icons/category-books.png', 'Active', '2026-07-27 07:01:16'),
('C003', 'Household & Living', 'Dorm essentials, mini kettles, and lamps', 'assets/icons/category-furniture.png', 'Active', '2026-07-27 07:01:16'),
('C004', 'Sports Equipment', 'Rackets, balls, and athletic gear', 'assets/icons/category-sports.png', 'Active', '2026-07-27 07:01:16'),
('C885', 'book', 'this book is for everyone', 'assets/icons/category-books.png', 'Active', '2026-08-13 14:51:42'),
('C886', 'sport', 'sportsss', 'assets/icons/category-sports.png', 'Active', '2026-08-13 14:57:53'),
('C887', 'Creative & Media', 'Reusable media and presentation equipment for campus activities.', 'assets/icons/category-music.png', 'Active', '2026-08-22 08:41:37'),
('C888', 'Event Supplies', 'Reusable supplies for student events and campus activities.', 'assets/icons/category-others.png', 'Active', '2026-08-23 08:41:37'),
('C889', 'Archived Accessories', 'Inactive category retained because historical asset records are linked to it.', 'assets/icons/category-electronics.png', 'Inactive', '2026-07-26 08:41:37');

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
('ET001', 'U004', 50, 'Earned', 'Mission', 'MS001', '2026-07-27 07:01:17'),
('ET002', 'U004', -100, 'Spent', 'RewardRedemption', 'RR001', '2026-07-27 07:01:17'),
('ET003', 'U003', 100, 'Earned', 'Exchange', 'AST001', '2026-07-27 07:01:17'),
('ET884', 'U004', -5, 'Spent', 'BorrowingPenalty', 'TR001', '2026-08-13 15:48:20'),
('ET889', 'U004', 50, 'Earned', 'Mission', 'M001', '2026-08-19 14:44:37'),
('ET890', 'U004', 100, 'Earned', 'Mission', 'M002', '2026-08-19 14:45:53'),
('ET891', 'U004', 123, 'Earned', 'Mission', 'M004', '2026-08-20 06:18:11'),
('ET892', 'U005', 50, 'Earned', 'Mission', 'MS001', '2026-08-25 06:01:56'),
('ET893', 'U004', 100, 'Earned', 'Mission', 'MS002', '2026-08-25 06:36:19'),
('ET894', 'U004', 123, 'Earned', 'Mission', 'MS004', '2026-08-25 06:36:19'),
('ET895', 'U990', 50, 'Earned', 'Mission', 'MS001', '2026-08-05 08:41:37');

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
('M001', 'Bring Your Own Bottle', 'Use a reusable water bottle on campus for a week', 'Upload photo of bottle at APU refill station', 50, 'Inactive'),
('M002', 'E-Waste Recycling Drive', 'Drop off old electronics or batteries at the hub', 'Upload photo of items dropped at recycling bin', 100, 'Active'),
('M003', 'Campus Eco-Clean Walk', 'Participate in the weekly campus litter collection', 'Upload selfie at event check-in point', 150, 'Active'),
('M004', 'aava', 'adfdghj', 'dfgh', 123, 'Active'),
('M005', 'ccc', 'azsxcvgb', 'zxcfvgbhn', 225, 'Inactive'),
('M006', 'E-Waste Recycling Drive', 'avdv', 'a', 212, 'Inactive'),
('M007', 'dsf', 'asdf', 'asdf', 134, 'Inactive'),
('M008', 'daad', 'sdassads', 'asda', 122, 'Active'),
('M009', 'saca', 'adgfghmjh', 'dfgghj', 23, 'Active');

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
('MS001', 'U005', 'M001', 'uploads/submissions/U005_M001_1787150024.png', '2026-08-19 14:33:44', 'Approved'),
('MS002', 'U004', 'M002', 'uploads/submissions/U004_M002_1787150732.png', '2026-08-19 14:45:32', 'Approved'),
('MS003', 'U004', 'M003', 'uploads/submissions/U004_M003_1787154917.png', '2026-08-19 15:55:17', 'Rejected'),
('MS004', 'U004', 'M004', 'uploads/submissions/U004_M004_1787206665.png', '2026-08-20 06:17:45', 'Approved'),
('S001', 'U004', 'M001', 'uploads/submissions/ms1_proof.jpg', '2026-07-27 07:01:16', ''),
('S002', 'U005', 'M002', 'uploads/submissions/ms2_proof.jpg', '2026-07-27 07:01:16', 'Pending'),
('S003', 'U004', 'M008', 'uploads/submissions/U004_M008_1787639877.png', '2026-08-25 06:37:57', 'Pending');

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
('N001', 'U004', 'Mission Approved!', 'Your submission for Bring Your Own Bottle was approved. +50 EcoPoints awarded.', 'Unread', '2026-07-27 07:01:17'),
('N002', 'U005', 'Borrow Request Update', 'Your request to borrow Java Programming Textbook has been approved.', 'Read', '2026-07-27 07:01:17'),
('N003', 'U004', 'Asset Listing Returned', 'Your asset listing AST003 (USB-C Fast Charger) was returned for correction.', 'Unread', '2026-08-10 14:49:36'),
('N004', 'U004', 'Asset Sent for Verification', 'Your asset listing AST003 (USB-C Fast Charger) has been sent for Sustainability Auditor verification.', 'Unread', '2026-08-10 14:56:31'),
('N005', 'U004', 'Asset Listing Returned', 'Your asset AST005 was returned for correction. Reason: please give a clearer image', 'Unread', '2026-08-10 15:28:23'),
('N006', 'U003', 'New Verification Request', 'A new mission submission is waiting for your verification.', 'Unread', '2026-08-19 02:00:00'),
('N007', 'U003', 'Mission Submission', 'A new mission submission has been submitted for verification.', 'Unread', '2026-08-19 01:30:00'),
('N008', 'U003', 'Low Reward Stock', 'The stock quantity of a reward is running low.', 'Unread', '2026-08-18 07:20:00'),
('N009', 'U003', 'Verification Completed', 'Mission submission S001 has been successfully verified.', 'Read', '2026-08-18 03:45:00'),
('N010', 'U003', 'System Maintenance', 'Scheduled system maintenance will take place on 20 August 2026.', 'Read', '2026-08-17 08:00:00'),
('N1006', 'U004', 'New Mission Available!', 'A new mission \"asd\" is available. Complete it to earn 12 EcoPoints.', 'Unread', '2026-08-19 15:51:15'),
('N1007', 'U005', 'New Mission Available!', 'A new mission \"asd\" is available. Complete it to earn 12 EcoPoints.', 'Unread', '2026-08-19 15:51:15'),
('N1008', 'U006', 'New Mission Available!', 'A new mission \"asd\" is available. Complete it to earn 12 EcoPoints.', 'Unread', '2026-08-19 15:51:15'),
('N1029', 'U004', 'New Reward Available!', 'A new reward \"qweqwe\" is now available for redemption. It costs 1000 EcoPoints.', 'Unread', '2026-08-19 15:54:55'),
('N1030', 'U005', 'New Reward Available!', 'A new reward \"qweqwe\" is now available for redemption. It costs 1000 EcoPoints.', 'Unread', '2026-08-19 15:54:55'),
('N1031', 'U006', 'New Reward Available!', 'A new reward \"qweqwe\" is now available for redemption. It costs 1000 EcoPoints.', 'Unread', '2026-08-19 15:54:55'),
('N1052', 'U004', 'Mission Rejected', 'Your mission \"Campus Eco-Clean Walk\" has been rejected by the auditor. Please check the remarks.', 'Unread', '2026-08-19 15:55:59'),
('N1053', 'U004', 'New Mission Available!', 'A new mission \"saca\" is available. Complete it to earn 23 EcoPoints.', 'Unread', '2026-08-20 06:10:49'),
('N1054', 'U005', 'New Mission Available!', 'A new mission \"saca\" is available. Complete it to earn 23 EcoPoints.', 'Unread', '2026-08-20 06:10:49'),
('N1055', 'U006', 'New Mission Available!', 'A new mission \"saca\" is available. Complete it to earn 23 EcoPoints.', 'Unread', '2026-08-20 06:10:49'),
('N1076', 'U004', 'Mission Approved!', 'Your mission \"aava\" has been approved by the auditor.', 'Unread', '2026-08-20 06:18:11'),
('N1077', 'U004', 'New Reward Available!', 'A new reward \"adfaf\" is now available for redemption. It costs 213 EcoPoints.', 'Unread', '2026-08-20 07:23:17'),
('N1078', 'U005', 'New Reward Available!', 'A new reward \"adfaf\" is now available for redemption. It costs 213 EcoPoints.', 'Unread', '2026-08-20 07:23:17'),
('N1079', 'U006', 'New Reward Available!', 'A new reward \"adfaf\" is now available for redemption. It costs 213 EcoPoints.', 'Unread', '2026-08-20 07:23:17'),
('N1100', 'U005', 'Asset Resubmitted Successfully', 'Your asset Data Structures Textbook (A006) has been resubmitted successfully and is pending manager review.', 'Unread', '2026-08-25 05:57:26'),
('N1101', 'U002', 'Asset Resubmitted for Review', 'Teo Miao Miao (U005) resubmitted Data Structures Textbook (A006). The asset is pending review.', 'Read', '2026-08-25 05:57:26'),
('N1102', 'U001', 'New Member Complaint', 'Report R001 was submitted by member U004.', 'Read', '2026-08-25 06:34:48'),
('N1103', 'U004', 'Complaint Submitted', 'Your complaint R001 was submitted successfully and is waiting for administrator review.', 'Unread', '2026-08-25 06:34:48'),
('N1108', 'U003', 'Mission Completed', 'Tan Miao Miao completed daad (M008). Mission ready for approval.', 'Read', '2026-08-25 06:37:57'),
('N1109', 'U004', 'Mission Submitted Successfully', 'Your proof for daad (M008) is waiting for auditor approval.', 'Unread', '2026-08-25 06:37:57'),
('N1110', 'U004', 'Reservation Confirmed', 'Your request TR970 for Wireless Optical Mouse was approved. Pickup: 25 Aug 2026. Return by: 28 Aug 2026. Reservation protection begins 18 Aug 2026.', 'Unread', '2026-08-25 08:20:19'),
('N1111', 'U005', 'Reservation Confirmed', 'Your request TR971 for 65W USB-C Charger was approved. Pickup: 26 Aug 2026. Return by: 29 Aug 2026. Reservation protection begins 19 Aug 2026.', 'Unread', '2026-08-25 08:20:33'),
('N1120', 'U002', 'New Asset Listing Submission', 'Asset A986 (USB-C Multiport Hub) is waiting for Resource Manager review.', 'Unread', '2026-08-25 08:40:37'),
('N1121', 'U002', 'Urgent Borrowing Request', 'Transaction TR981 for HDMI Presentation Adapter has pickup scheduled for today.', 'Unread', '2026-08-25 08:39:37'),
('N1122', 'U002', 'Overdue Return Alert', 'Transaction TR987 for Database Revision Book is overdue and has not been returned.', 'Unread', '2026-08-25 08:38:37'),
('N1123', 'U002', 'Reservation Protection Attention', 'Transaction TR984 for Portable Projector is protected and waiting for pickup confirmation.', 'Read', '2026-08-25 08:37:37'),
('N1124', 'U002', 'Report Data Updated', 'New transaction and asset activity is available for Resource Manager reporting.', 'Unread', '2026-08-25 08:36:37'),
('N1125', 'U990', 'Overdue Asset Return Reminder', 'Transaction TR987: Your borrowed asset Database Revision Book is overdue. Please return the asset as soon as possible.', 'Unread', '2026-08-24 08:41:37'),
('N1126', 'U004', 'Asset Listing Returned', 'Your asset listing A986 (USB-C Multiport Hub) was returned for correction. Reason: Information incomplete', 'Unread', '2026-08-25 09:26:52'),
('N1127', 'U005', 'Asset Sent for Verification', 'Your asset listing A987 (Scientific Calculator) passed Resource Manager review and has been sent to the Sustainability Auditor for verification.', 'Unread', '2026-08-25 09:33:13'),
('N1128', 'U003', 'Asset Ready for Verification', 'Asset A987 (Scientific Calculator), submitted by Teo Miao Miao (U005), has passed Resource Manager review and is ready for verification. Verification ID: V997.', 'Unread', '2026-08-25 09:33:13'),
('N885', 'U005', 'Asset Sent for Verification', 'Asset A008 has been sent for Sustainability Auditor verification.', 'Unread', '2026-08-13 13:00:48'),
('N888', 'U005', 'Borrowing Request Rejected', 'Your request TR002 for Dell Wireless Mouse was rejected. Reason: dont have pickup date and request date', 'Unread', '2026-08-13 15:12:34'),
('N903', 'U004', 'Asset Return Completed', 'Return for Java Programming Textbook completed. Returned condition: Good. Asset status: Available. 5 EcoPoints were deducted. New balance: 295. Reason: damange a little', 'Unread', '2026-08-13 15:48:20'),
('N924', 'U004', 'Mission Approved!', 'Your mission \"Bring Your Own Bottle\" has been approved by the auditor.', 'Unread', '2026-08-19 14:44:37'),
('N925', 'U004', 'Mission Approved!', 'Your mission \"E-Waste Recycling Drive\" has been approved by the auditor.', 'Unread', '2026-08-19 14:45:53'),
('N926', 'U004', 'New Achievement Badge!', 'A new badge \"dfbdgfh\" is now available. Requirement: Get 12 approved assets', 'Unread', '2026-08-19 14:57:37'),
('N927', 'U005', 'New Achievement Badge!', 'A new badge \"dfbdgfh\" is now available. Requirement: Get 12 approved assets', 'Unread', '2026-08-19 14:57:37'),
('N928', 'U006', 'New Achievement Badge!', 'A new badge \"dfbdgfh\" is now available. Requirement: Get 12 approved assets', 'Unread', '2026-08-19 14:57:37'),
('N949', 'U004', 'Asset Sent for Verification', 'Your asset listing A960 (Wireless Mouse) passed Resource Manager review and has been sent to the Sustainability Auditor for verification.', 'Unread', '2026-08-19 15:17:15'),
('N950', 'U003', 'Asset Ready for Verification', 'Asset A960 (Wireless Mouse), submitted by Tan Miao Miao (U004), has passed Resource Manager review and is ready for verification. Verification ID: V008.', 'Read', '2026-08-19 15:17:15'),
('N951', 'U004', 'Asset Approved!', 'Your asset \"Wireless Mouse\" has been approved by the auditor.', 'Unread', '2026-08-19 15:18:22'),
('N952', 'U004', 'Asset Listing Returned', 'Your asset listing A964 (Scientific Calculator) was returned for correction. Reason: svsv', 'Unread', '2026-08-19 15:19:34'),
('N954', 'U004', 'Asset Sent for Verification', 'Your asset listing A961 (Laptop Cooling Pad) passed Resource Manager review and has been sent to the Sustainability Auditor for verification.', 'Unread', '2026-08-19 15:22:44'),
('N955', 'U003', 'Asset Ready for Verification', 'Asset A961 (Laptop Cooling Pad), submitted by Tan Miao Miao (U004), has passed Resource Manager review and is ready for verification. Verification ID: V009.', 'Read', '2026-08-19 15:22:44'),
('N956', 'U004', 'Asset Sent for Verification', 'Your asset listing A962 (USB Webcam) passed Resource Manager review and has been sent to the Sustainability Auditor for verification.', 'Unread', '2026-08-19 15:22:48'),
('N957', 'U003', 'Asset Ready for Verification', 'Asset A962 (USB Webcam), submitted by Tan Miao Miao (U004), has passed Resource Manager review and is ready for verification. Verification ID: V010.', 'Read', '2026-08-19 15:22:48'),
('N958', 'U004', 'Asset Sent for Verification', 'Your asset listing A963 (Portable Bluetooth Speaker) passed Resource Manager review and has been sent to the Sustainability Auditor for verification.', 'Unread', '2026-08-19 15:22:51'),
('N959', 'U003', 'Asset Ready for Verification', 'Asset A963 (Portable Bluetooth Speaker), submitted by Tan Miao Miao (U004), has passed Resource Manager review and is ready for verification. Verification ID: V011.', 'Read', '2026-08-19 15:22:51'),
('N960', 'U004', 'New Mission Available!', 'A new mission \"aava\" is available. Complete it to earn 123 EcoPoints.', 'Unread', '2026-08-19 15:26:43'),
('N961', 'U005', 'New Mission Available!', 'A new mission \"aava\" is available. Complete it to earn 123 EcoPoints.', 'Unread', '2026-08-19 15:26:43'),
('N962', 'U006', 'New Mission Available!', 'A new mission \"aava\" is available. Complete it to earn 123 EcoPoints.', 'Unread', '2026-08-19 15:26:43'),
('N983', 'U004', 'New Mission Available!', 'A new mission \"daad\" is available. Complete it to earn 122 EcoPoints.', 'Unread', '2026-08-19 15:34:04'),
('N984', 'U005', 'New Mission Available!', 'A new mission \"daad\" is available. Complete it to earn 122 EcoPoints.', 'Unread', '2026-08-19 15:34:04'),
('N985', 'U006', 'New Mission Available!', 'A new mission \"daad\" is available. Complete it to earn 122 EcoPoints.', 'Unread', '2026-08-19 15:34:04'),
('NOT006', 'U005', 'Asset Listing Returned', 'Your asset AST006 (Data Structures Textbook) was returned for correction. Reason: explain furtherr', 'Unread', '2026-08-10 15:39:17'),
('NOT007', 'U004', 'Asset Sent for Verification', 'Asset AST007 has been sent for Sustainability Auditor verification.', 'Unread', '2026-08-11 13:19:16');

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
('R001', 'U004', 'V012', 'cannot use', NULL, 'Pending', '2026-08-25 06:34:48');

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
('RR001', 'U004', 'R001', '2026-07-27 07:01:16'),
('RR002', 'U003', 'R002', '2026-07-27 07:01:16');

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
('R001', 'Campus Cafe RM5 Voucher', 'Redeemable at APU main cafeteria food stalls', 'campus_cafe_rm5.png', 100, 0, 'Available'),
('R002', 'CirculAPU Tote Bag', 'Eco-friendly canvas bag with official logo', 'circulapu_tote_bag.png', 200, 1, 'Available'),
('R003', 'APU Parking Pass (1 Day)', 'Single-day parking pass waiver for campus garage', 'apu_parking_pass.png', 300, 5, 'Available'),
('R004', 'e', 'dw', 'reward_e_1787154866.png', 12, 12, 'Unavailable'),
('R005', 'qweqwe', '1wee', 'reward_qweqwe_1787154895.png', 1000, 12, 'Available'),
('R006', 'adfaf', 'adfsdf', 'reward_adfaf_1787210597.png', 213, 123, 'Available');

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
('LOG001', 'U001', 'Create', 'Category', NULL, 'Created new system category: Electronics', '2026-07-27 07:01:17'),
('LOG002', 'U001', 'Update', 'User', 'U004', 'Adjusted EcoPoints balance for user U004 (+50)', '2026-07-27 07:01:17'),
('LOG003', 'U001', 'Update', 'User', 'U006', 'Updated account status for user U006 to Suspended', '2026-07-27 07:01:17'),
('LOG004', 'U002', 'Update', 'Asset', 'AST001', 'Updated asset listing: Dell Wireless Mouse', '2026-08-10 10:37:48'),
('LOG005', 'U002', 'Update', 'Asset', 'AST001', 'Updated asset listing: Dell Wireless Mouse', '2026-08-10 10:38:13'),
('LOG006', 'U002', 'Create', 'Asset', 'AST004', 'Created asset listing: Portable HDMI adapter', '2026-08-10 11:12:08'),
('LOG007', 'U002', 'Update', 'Asset', 'AST004', 'Updated asset listing: Portable HDMI adapter', '2026-08-10 11:14:18'),
('LOG008', 'U002', 'Update', 'Asset', 'AST004', 'Updated asset listing: Portable HDMI adapter', '2026-08-10 11:14:33'),
('LOG009', 'U002', 'Delete', 'Asset', 'AST004', 'Removed asset listing: Portable HDMI adapter', '2026-08-10 13:53:33'),
('LOG010', 'U002', 'Update', 'Asset', 'AST003', 'Updated asset submission during review: USB-C Fast Charger', '2026-08-10 14:48:02'),
('LOG011', 'U002', 'Update', 'Asset', 'AST003', 'Returned asset listing for correction: USB-C Fast Charger', '2026-08-10 14:49:36'),
('LOG012', 'U002', 'Update', 'Asset', 'AST003', 'Sent asset listing for verification: USB-C Fast Charger', '2026-08-10 14:56:31'),
('LOG013', 'U002', 'Update', 'Asset', 'AST005', 'Returned AST005 for correction. Reason: please give a clearer image', '2026-08-10 15:28:23'),
('LOG014', 'U002', 'Update', 'Asset', 'AST006', 'Returned AST006 for correction. Reason: explain furtherr', '2026-08-10 15:39:17'),
('LOG015', 'U002', 'Delete', 'Asset', 'AST005', 'Removed asset listing: Wireless Keyboard', '2026-08-10 15:45:15'),
('LOG016', 'U002', 'Delete', 'Asset', 'AST001', 'Removed asset listing: Dell Wireless Mouse', '2026-08-11 12:07:18'),
('LOG017', 'U002', 'Delete', 'Asset', 'AST003', 'Removed asset listing: USB-C Fast Charger', '2026-08-11 12:21:00'),
('LOG018', 'U002', 'Update', 'Asset', 'AST006', 'Updated asset information: Data Structures Textbook', '2026-08-11 12:27:00'),
('LOG019', 'U002', 'Update', 'Asset', 'AST006', 'Updated asset information: Data Structures Textbook', '2026-08-11 12:27:07'),
('LOG020', 'U002', 'Update', 'Asset', 'AST007', 'Updated asset submission during review: USB-C Charging Cable', '2026-08-11 12:27:14'),
('LOG021', 'U002', 'Update', 'Asset', 'AST007', 'Updated asset submission during review: USB-C Charging Cable', '2026-08-11 12:27:21'),
('LOG022', 'U002', 'Update', 'Asset', 'AST007', 'Updated asset submission during review: USB-C Charging Cable', '2026-08-11 12:36:39'),
('LOG023', 'U002', 'Update', 'Asset', 'AST007', 'Updated asset submission during review: USB-C Charging Cable', '2026-08-11 12:53:57'),
('LOG024', 'U002', 'Update', 'Asset', 'AST007', 'Updated asset submission during review: USB-C Charging Cable', '2026-08-11 12:54:01'),
('LOG025', 'U002', 'Submit', 'Asset', 'AST007', 'Sent asset listing for verification: USB-C Charging Cable', '2026-08-11 13:19:16'),
('LOG026', 'U003', 'Delete', 'Verification', 'V003', 'Deleted verification record: V003', '2026-08-11 16:35:32'),
('LOG028', 'U003', 'Update', 'Verification', 'V006', 'Verification V006 was Approved', '2026-08-12 03:28:48'),
('LOG1000', 'U003', 'Approve', 'Verification', 'V008', 'Approved verification V008', '2026-08-19 15:18:22'),
('LOG1001', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-19 15:18:56'),
('LOG1002', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-19 15:19:09'),
('LOG1003', 'U002', 'Update', 'Asset', 'A964', 'Returned A964 for correction. Reason: svsv', '2026-08-19 15:19:34'),
('LOG1004', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-19 15:19:43'),
('LOG1005', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 15:19:55'),
('LOG1006', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-19 15:20:25'),
('LOG1008', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 15:22:14'),
('LOG1009', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-19 15:22:36'),
('LOG1010', 'U002', 'Submit', 'Asset', 'A961', 'Sent asset listing for verification: Laptop Cooling Pad', '2026-08-19 15:22:44'),
('LOG1011', 'U002', 'Submit', 'Asset', 'A962', 'Sent asset listing for verification: USB Webcam', '2026-08-19 15:22:48'),
('LOG1012', 'U002', 'Submit', 'Asset', 'A963', 'Sent asset listing for verification: Portable Bluetooth Speaker', '2026-08-19 15:22:51'),
('LOG1013', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 15:22:59'),
('LOG1014', 'U003', 'Create', 'Mission', 'M004', 'Created new mission: aava', '2026-08-19 15:26:43'),
('LOG1015', 'U003', 'Create', 'Mission', 'M005', 'Created new mission: ccc', '2026-08-19 15:26:58'),
('LOG1016', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-19 15:27:09'),
('LOG1017', 'U006', 'Login', 'User', 'U006', 'Logged in to system', '2026-08-19 15:27:21'),
('LOG1018', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 15:27:55'),
('LOG1019', 'U003', 'Create', 'Mission', 'M006', 'Created new mission: E-Waste Recycling Drive', '2026-08-19 15:28:24'),
('LOG1020', 'U003', 'Create', 'Mission', 'M007', 'Created new mission: dsf', '2026-08-19 15:33:33'),
('LOG1021', 'U003', 'Create', 'Mission', 'M008', 'Created new mission: daad', '2026-08-19 15:34:04'),
('LOG1022', 'U003', 'Create', 'Mission', 'M009', 'Created new mission: asd', '2026-08-19 15:51:15'),
('LOG1023', 'U003', 'Delete', 'Mission', 'M009', 'Deleted mission: asd', '2026-08-19 15:53:49'),
('LOG1024', 'U003', 'Create', 'Reward', 'R004', 'Created reward: e', '2026-08-19 15:54:26'),
('LOG1025', 'U003', 'Create', 'Reward', 'R005', 'Created reward: qweqwe', '2026-08-19 15:54:55'),
('LOG1026', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-19 15:55:02'),
('LOG1027', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 15:55:24'),
('LOG1028', 'U003', 'Reject', 'Verification', 'V012', 'Rejected verification V012', '2026-08-19 15:55:59'),
('LOG1029', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-19 15:56:18'),
('LOG1030', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 15:56:32'),
('LOG1031', 'U003', 'Update', 'Reward', 'R001', 'Updated reward: Campus Cafe RM5 Voucher', '2026-08-19 16:01:02'),
('LOG1032', 'U003', 'Update', 'Reward', 'R002', 'Updated reward: CirculAPU Tote Bag', '2026-08-19 16:01:13'),
('LOG1033', 'U003', 'Delete', 'Badge', 'AB004', 'Deleted achievement badge: dfbdgfh', '2026-08-19 16:22:28'),
('LOG1034', 'U003', 'Create', 'Mission', 'M009', 'Created new mission: saca', '2026-08-20 06:10:49'),
('LOG1035', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-20 06:14:45'),
('LOG1036', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-20 06:17:59'),
('LOG1037', 'U003', 'Approve', 'Verification', 'V013', 'Approved verification V013', '2026-08-20 06:18:11'),
('LOG1038', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-20 06:18:25'),
('LOG1039', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-20 07:22:58'),
('LOG1040', 'U003', 'Create', 'Reward', 'R006', 'Created reward: adfaf', '2026-08-20 07:23:17'),
('LOG1041', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-20 07:23:25'),
('LOG1042', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-25 06:34:28'),
('LOG1043', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-25 06:53:38'),
('LOG1044', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-25 07:06:56'),
('LOG1045', 'U001', 'Login', 'User', 'U001', 'Logged in to system', '2026-08-25 07:08:24'),
('LOG1046', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-25 07:10:43'),
('LOG1047', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-25 07:14:19'),
('LOG1050', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-25 07:21:28'),
('LOG1051', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-25 07:29:41'),
('LOG1052', 'U002', 'Approve', 'Transaction', 'TR970', 'Approved borrowing request for Wireless Optical Mouse; asset reserved inside the 7-day protection window', '2026-08-25 08:20:19'),
('LOG1053', 'U002', 'Approve', 'Transaction', 'TR971', 'Approved borrowing request for 65W USB-C Charger; asset reserved inside the 7-day protection window', '2026-08-25 08:20:33'),
('LOG1060', 'U002', 'Update', 'Asset', 'A986', 'Reviewed incoming asset information for USB-C Multiport Hub.', '2026-08-25 08:40:37'),
('LOG1061', 'U002', 'Create', 'Category', 'C887', 'Created category: Creative & Media.', '2026-08-25 08:39:37'),
('LOG1062', 'U002', 'Approve', 'Transaction', 'TR984', 'Approved borrowing request for Portable Projector; reservation protection is active.', '2026-08-25 08:38:37'),
('LOG1063', 'U002', 'Assign', 'Transaction', 'TR986', 'Pickup confirmed; borrowing started for Graphic Calculator.', '2026-08-25 08:37:37'),
('LOG1064', 'U002', 'Update', 'Borrowing', 'B987', 'Overdue borrowing identified for Database Revision Book.', '2026-08-25 08:36:37'),
('LOG1065', 'U002', 'Approve', 'Transaction', 'TR985', 'Approved future reservation for Adjustable Laptop Stand; asset remains Available before protection starts.', '2026-08-24 08:41:37'),
('LOG1066', 'U002', 'Approve', 'Transaction', 'TR989', 'Approved upcoming reservation for Shared Camera Tripod.', '2026-08-24 08:41:37'),
('LOG1067', 'U002', 'Update', 'Asset', 'A983', 'Confirmed Sealed USB-C Cable is eligible for normal listing management.', '2026-08-23 08:41:37'),
('LOG1068', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-25 08:49:37'),
('LOG1069', 'U002', 'Update', 'Asset', 'A981', 'Updated asset information: USB-C Fast Charger', '2026-08-25 08:56:27'),
('LOG1070', 'U002', 'Update', 'Asset', 'A986', 'Returned A986 for correction. Reason: Information incomplete', '2026-08-25 09:26:52'),
('LOG1071', 'U002', 'Submit', 'Asset', 'A987', 'Sent asset listing for verification: Scientific Calculator', '2026-08-25 09:33:13'),
('LOG145', 'U003', 'Update', 'Mission', 'M001', 'Updated mission: Bring Your Own Bottle', '2026-08-19 15:28:09'),
('LOG365', 'U003', 'Update', 'Verification', 'V006', 'Updated verification record: V006', '2026-08-12 09:59:28'),
('LOG381', 'U003', 'Update', 'Verification', 'V004', 'Updated verification record: V004', '2026-08-11 17:22:34'),
('LOG462', 'U003', 'Update', 'Verification', 'V004', 'Verification V004 was Approved', '2026-08-11 17:21:54'),
('LOG463', 'U003', 'Delete', 'Verification', 'V007', 'Deleted verification record: V007', '2026-08-12 03:31:42'),
('LOG882', 'U003', 'Update', 'Verification', 'V004', 'Updated verification remarks: V004', '2026-08-12 10:09:42'),
('LOG883', 'U002', 'Create', 'Category', 'C005', 'Created category: Game', '2026-08-13 03:05:47'),
('LOG884', 'U002', 'Delete', 'Category', 'C005', 'Permanently deleted category: Game', '2026-08-13 03:06:00'),
('LOG885', 'U003', 'Create', 'Mission', 'M010', 'Created new mission: wefw', '2026-08-13 04:17:00'),
('LOG886', 'U003', 'Create', 'Mission', 'M011', 'Created new mission: wqf', '2026-08-13 04:26:18'),
('LOG887', 'U003', 'Create', 'Mission', 'M012', 'Created new mission: 2q3werty', '2026-08-13 04:43:10'),
('LOG899', 'U002', 'Submit', 'Asset', 'A008', 'Sent asset listing for verification: Scientific Calculator', '2026-08-13 13:00:48'),
('LOG909', 'U002', 'Create', 'Category', 'C885', 'Created category: book', '2026-08-13 14:51:42'),
('LOG910', 'U002', 'Create', 'Category', 'C886', 'Created category: hhh', '2026-08-13 14:51:54'),
('LOG911', 'U002', 'Update', 'Category', 'C886', 'Deactivated category: hhh', '2026-08-13 14:56:04'),
('LOG912', 'U002', 'Delete', 'Category', 'C886', 'Permanently deleted category: hhh', '2026-08-13 14:56:40'),
('LOG913', 'U002', 'Update', 'Category', 'C885', 'Deactivated category: book', '2026-08-13 14:56:59'),
('LOG914', 'U002', 'Update', 'Category', 'C885', 'Updated category: book', '2026-08-13 14:57:15'),
('LOG915', 'U002', 'Create', 'Category', 'C886', 'Created category: sport', '2026-08-13 14:57:53'),
('LOG916', 'U002', 'Update', 'Category', 'C886', 'Deactivated category: sport', '2026-08-13 14:58:24'),
('LOG918', 'U002', 'Update', 'Category', 'C886', 'Reactivated category: sport', '2026-08-13 15:01:04'),
('LOG920', 'U002', 'Update', 'Category', 'C886', 'Updated category: sport', '2026-08-13 15:03:01'),
('LOG921', 'U002', 'Reject', 'Transaction', 'TR002', 'Rejected borrowing request for Dell Wireless Mouse. Reason: dont have pickup date and request date', '2026-08-13 15:12:34'),
('LOG936', 'U002', 'Update', 'Transaction', 'TR001', 'Return completed for Java Programming Textbook: Like New → Good; asset Available; deducted 5 EcoPoints (new balance: 295). Reason: damange a little; returned overdue.', '2026-08-13 15:48:20'),
('LOG954', 'U002', 'Delete', 'Asset', 'A007', 'Removed asset listing: USB-C Charging Cable', '2026-08-14 10:54:59'),
('LOG975', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-17 13:15:36'),
('LOG976', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-19 14:28:41'),
('LOG978', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 14:30:29'),
('LOG979', 'U005', 'Login', 'User', 'U005', 'Logged in to system', '2026-08-19 14:31:46'),
('LOG980', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 14:33:03'),
('LOG981', 'U005', 'Login', 'User', 'U005', 'Logged in to system', '2026-08-19 14:33:29'),
('LOG982', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 14:33:51'),
('LOG983', 'U003', 'Approve', 'Verification', 'V001', 'Approved verification V001', '2026-08-19 14:44:37'),
('LOG984', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-19 14:44:48'),
('LOG985', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 14:45:41'),
('LOG986', 'U003', 'Approve', 'Verification', 'V007', 'Approved verification V007', '2026-08-19 14:45:53'),
('LOG987', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-19 14:46:02'),
('LOG988', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-19 14:48:24'),
('LOG989', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 14:56:15'),
('LOG990', 'U003', 'Create', 'Badge', 'AB004', 'Created achievement badge: dfbdgfh', '2026-08-19 14:57:37'),
('LOG991', 'U004', 'Login', 'User', 'U004', 'Logged in to system', '2026-08-19 14:57:47'),
('LOG992', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-19 14:58:01'),
('LOG993', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 14:59:53'),
('LOG994', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-19 15:07:57'),
('LOG995', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 15:10:16'),
('LOG996', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-19 15:17:06'),
('LOG997', 'U002', 'Submit', 'Asset', 'A960', 'Sent asset listing for verification: Wireless Mouse', '2026-08-19 15:17:15'),
('LOG998', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-19 15:17:25'),
('LOG999', 'U003', 'Login', 'User', 'U003', 'Logged in to system', '2026-08-19 15:17:35');

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
('T001', 'Eco Beginner', 'Starting milestone for new sustainability participants', 'eco_beginner.png', 0),
('T002', 'Green Enthusiast', 'Active participant contributing to campus recycling', 'green_enthusiast.png', 100),
('T003', 'Eco Champion', 'High-impact user making frequent asset exchanges', 'eco_champion.png', 300),
('T004', 'Sustainability Leader', 'Top tier user leading campus eco-initiatives', 'sustainability_leader.png', 600),
('T005', 'Eco Legend', 'Highest tier for exceptional and consistent sustainability participation.', 'eco_legend.png', 1000);

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
('TR001', 'A002', 'U004', '2026-07-27 07:01:17', 'Completed', 'Request granted for 14-day study period.', '2026-07-27 07:01:17', 'Hi, I need this textbook for my upcoming exam week.'),
('TR002', 'A001', 'U005', '2026-07-27 07:01:17', 'Rejected', 'dont have pickup date and request date', '2026-08-13 15:12:34', 'Can I borrow this mouse for a few days?'),
('TR970', 'A972', 'U004', '2026-08-25 06:19:17', 'Approved', '', '2026-08-25 08:20:19', 'I need the mouse for a presentation session today.'),
('TR971', 'A973', 'U005', '2026-08-25 04:19:17', 'Approved', '', '2026-08-25 08:20:33', 'I would like to borrow the charger for tomorrow.'),
('TR972', 'A974', 'U004', '2026-08-24 08:19:17', 'Pending', NULL, NULL, 'I need the textbook for revision later this week.'),
('TR973', 'A975', 'U004', '2026-08-24 08:19:17', 'Approved', 'Borrowing request approved. Asset reserved for pickup.', '2026-08-25 05:19:17', 'I would like to collect the charger today for coursework use.'),
('TR974', 'A976', 'U005', '2026-08-23 08:19:17', 'Approved', 'Borrowing request approved within the reservation protection period.', '2026-08-24 08:19:17', 'I need the textbook for my software engineering assignment.'),
('TR975', 'A977', 'U005', '2026-08-23 08:19:17', 'Approved', 'Future borrowing request approved.', '2026-08-24 08:19:17', 'I would like to reserve the charging cable for next week.'),
('TR976', 'A978', 'U005', '2026-08-20 08:19:17', 'In Progress', 'Pickup confirmed and borrowing started.', '2026-08-22 08:19:17', 'I need the mouse for several study sessions.'),
('TR977', 'A979', 'U006', '2026-08-13 08:19:17', 'Overdue', 'Borrowing is overdue and awaiting return.', '2026-08-15 08:19:17', 'I need the reference book for my data structures revision.'),
('TR978', 'A980', 'U006', '2026-08-10 08:19:17', 'Completed', 'Asset returned in good condition.', '2026-08-11 08:19:17', 'I would like to borrow this textbook for revision.'),
('TR979', 'A981', 'U004', '2026-08-16 08:19:17', 'Rejected', 'Request was no longer required by the member.', '2026-08-17 08:19:17', 'I may need the charger for a short study session.'),
('TR980', 'A982', 'U005', '2026-08-17 08:19:17', 'Cancelled', 'Reservation cancelled at the member request.', '2026-08-18 08:19:17', 'I would like to reserve this textbook for next week.'),
('TR981', 'A988', 'U004', '2026-08-25 08:39:37', 'Pending', NULL, NULL, 'I need the adapter for a presentation today.'),
('TR982', 'A989', 'U005', '2026-08-25 08:37:37', 'Pending', NULL, NULL, 'I need the reference book for tomorrow study session.'),
('TR983', 'A990', 'U004', '2026-08-25 08:35:37', 'Pending', NULL, NULL, 'The requested pickup date has already passed.'),
('TR984', 'A991', 'U004', '2026-08-24 08:41:37', 'Approved', 'Borrowing request approved and protected for pickup.', '2026-08-25 08:33:37', 'I need the projector for a campus presentation.'),
('TR985', 'A992', 'U990', '2026-08-23 08:41:37', 'Approved', 'Future reservation confirmed outside the protection period.', '2026-08-24 08:41:37', 'I would like to reserve the laptop stand for a future study session.'),
('TR986', 'A993', 'U004', '2026-08-21 08:41:37', 'In Progress', 'Pickup confirmed and borrowing started.', '2026-08-22 08:41:37', 'I need the calculator for coursework.'),
('TR987', 'A994', 'U990', '2026-08-13 08:41:37', 'In Progress', 'Pickup confirmed; return is now overdue.', '2026-08-15 08:41:37', 'I need the book for database revision.'),
('TR988', 'A995', 'U991', '2026-08-18 08:41:37', 'In Progress', 'Current borrowing is active.', '2026-08-19 08:41:37', 'I need the tripod for a media project.'),
('TR989', 'A995', 'U991', '2026-08-23 08:41:37', 'Approved', 'Upcoming protected reservation confirmed.', '2026-08-24 08:41:37', 'I need the tripod again for the next project session.');

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
('U001', 'Lee Miao Miao', 'admin@mail.apu.edu.my', 'password123', 'Admin', 'Active', 6070, '2026-07-27 07:01:16'),
('U002', 'Lim Miao Miao', 'manager@mail.apu.edu.my', 'password123', 'Manager', 'Active', 120, '2026-07-27 07:01:16'),
('U003', 'Ooi Mian Miao', 'auditor@mail.apu.edu.my', 'password123', 'Auditor', 'Active', 450, '2026-07-27 07:01:16'),
('U004', 'Tan Miao Miao', 'tan@mail.apu.edu.my', 'password123', 'Member', 'Active', 123, '2026-07-27 07:01:16'),
('U005', 'Teo Miao Miao', 'teo@mail.apu.edu.my', 'password123', 'Member', 'Active', 85, '2026-07-27 07:01:16'),
('U006', 'Jia Qi Lim', 'limjiaqi735@gmail.com', '$2y$10$PQIKXR2iwXQnxpG0wMksXuX6Jr1FfQmWQfP7dBfBcsUQWuuPiNzFK', 'Member', 'Active', 0, '2026-07-28 05:57:29'),
('U990', 'Aisha Rahman', 'aisha.manual@apu.edu.my', 'password123', 'Member', 'Active', 250, '2026-05-27 08:41:37'),
('U991', 'Daniel Lee', 'daniel.manual@apu.edu.my', 'password123', 'Member', 'Active', 180, '2026-06-06 08:41:37');

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
('UAB001', 'U004', 'AB001', '2026-07-27 07:01:16'),
('UAB002', 'U003', 'AB001', '2026-07-27 07:01:16'),
('UAB003', 'U001', 'AB003', '2026-07-27 07:01:16');

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
('UT001', 'U001', 'T004', '2026-07-27 07:01:16'),
('UT002', 'U003', 'T003', '2026-07-27 07:01:16'),
('UT003', 'U004', 'T002', '2026-07-27 07:01:16'),
('UT004', 'U005', 'T001', '2026-07-27 07:01:16'),
('UT006', 'U006', 'T001', '2026-07-28 05:57:29');

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
('V001', 'U004', 'U003', 'MS001', 'MissionSubmission', 'Approved', 'cdwc', '2026-08-19 14:44:37'),
('V002', 'U004', 'U003', 'A001', 'Asset', 'Approved', 'Asset condition verified in person.', '2026-07-27 07:03:20'),
('V004', 'U005', 'U003', 'A002', 'Asset', 'Approved', 'In a very good conditions.', '2026-08-11 17:19:49'),
('V005', 'U006', NULL, 'A003', 'Asset', 'Pending', NULL, NULL),
('V006', 'U005', 'U003', 'A004', 'Asset', 'Rejected', 'Execllent', '2026-08-12 03:28:48'),
('V007', 'U004', 'U003', 'MS002', 'MissionSubmission', 'Approved', 'dqw', '2026-08-19 14:45:53'),
('V008', 'U004', 'U003', 'A960', 'Asset', 'Approved', 'dxfgfhjk', '2026-08-19 15:18:22'),
('V009', 'U004', NULL, 'A961', 'Asset', 'Pending', NULL, NULL),
('V010', 'U004', NULL, 'A962', 'Asset', 'Pending', NULL, NULL),
('V011', 'U004', NULL, 'A963', 'Asset', 'Pending', NULL, NULL),
('V012', 'U004', 'U003', 'MS003', 'MissionSubmission', 'Rejected', 'wewee', '2026-08-19 15:55:59'),
('V013', 'U004', 'U003', 'MS004', 'MissionSubmission', 'Approved', 'eee', '2026-08-20 06:18:11'),
('V015', 'U004', NULL, 'S003', 'MissionSubmission', 'Pending', NULL, '2026-08-25 06:37:57'),
('V971', 'U005', NULL, 'A971', 'Asset', 'Pending', NULL, NULL),
('V972', 'U005', 'U003', 'A972', 'Asset', 'Approved', 'Asset condition and listing details verified.', '2026-08-17 08:19:17'),
('V973', 'U004', 'U003', 'A973', 'Asset', 'Approved', 'Asset condition and listing details verified.', '2026-08-15 08:19:17'),
('V974', 'U005', 'U003', 'A974', 'Asset', 'Approved', 'Asset condition and listing details verified.', '2026-08-14 08:19:17'),
('V975', 'U005', 'U003', 'A975', 'Asset', 'Approved', 'Asset condition and listing details verified.', '2026-08-13 08:19:17'),
('V976', 'U004', 'U003', 'A976', 'Asset', 'Approved', 'Asset condition and listing details verified.', '2026-08-12 08:19:17'),
('V977', 'U004', 'U003', 'A977', 'Asset', 'Approved', 'Asset condition and listing details verified.', '2026-08-16 08:19:17'),
('V978', 'U004', 'U003', 'A978', 'Asset', 'Approved', 'Asset condition and listing details verified.', '2026-08-11 08:19:17'),
('V979', 'U005', 'U003', 'A979', 'Asset', 'Approved', 'Asset condition and listing details verified.', '2026-08-10 08:19:17'),
('V980', 'U004', 'U003', 'A980', 'Asset', 'Approved', 'Asset condition and listing details verified.', '2026-08-09 08:19:17'),
('V981', 'U005', 'U003', 'A981', 'Asset', 'Approved', 'Asset condition and listing details verified.', '2026-08-13 08:19:17'),
('V982', 'U004', 'U003', 'A982', 'Asset', 'Approved', 'Asset condition and listing details verified.', '2026-08-12 08:19:17'),
('V983', 'U005', 'U003', 'A983', 'Asset', 'Approved', 'Condition verified and suitable for circulation.', '2026-08-15 08:41:37'),
('V984', 'U004', 'U003', 'A984', 'Asset', 'Rejected', 'Condition does not meet circulation requirements.', '2026-08-05 08:41:37'),
('V985', 'U005', 'U003', 'A985', 'Asset', 'Approved', 'Asset details and condition verified.', '2026-08-20 08:41:37'),
('V986', 'U004', NULL, 'A986', 'Asset', 'Pending', NULL, NULL),
('V987', 'U005', NULL, 'A987', 'Asset', 'Pending', NULL, NULL),
('V988', 'U005', 'U003', 'A988', 'Asset', 'Approved', 'Asset verified for borrowing.', '2026-08-17 08:41:37'),
('V989', 'U004', 'U003', 'A989', 'Asset', 'Approved', 'Asset verified for borrowing.', '2026-08-17 08:41:37'),
('V990', 'U005', 'U003', 'A990', 'Asset', 'Approved', 'Asset verified for borrowing.', '2026-08-17 08:41:37'),
('V991', 'U005', 'U003', 'A991', 'Asset', 'Approved', 'Asset verified for borrowing.', '2026-08-16 08:41:37'),
('V992', 'U005', 'U003', 'A992', 'Asset', 'Approved', 'Asset verified for borrowing.', '2026-08-16 08:41:37'),
('V993', 'U005', 'U003', 'A993', 'Asset', 'Approved', 'Asset verified for borrowing.', '2026-08-15 08:41:37'),
('V994', 'U005', 'U003', 'A994', 'Asset', 'Approved', 'Asset verified for borrowing.', '2026-08-13 08:41:37'),
('V995', 'U004', 'U003', 'A995', 'Asset', 'Approved', 'Asset verified for borrowing.', '2026-08-13 08:41:37'),
('V996', 'U004', 'U003', 'A996', 'Asset', 'Approved', 'Historical asset verification retained.', '2026-08-05 08:41:37'),
('V997', 'U005', NULL, 'A987', 'Asset', 'Pending', NULL, NULL);

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
