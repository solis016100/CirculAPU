-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 11, 2026 at 07:18 PM
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
('A002', 'U005', 'C002', 'Java Programming Textbook', '8th Edition Java reference guide in good condition', 'Like New', 'uploads/assets/book.jpg', 'Borrowed', 'Published', '2026-07-27 07:01:16', '2026-08-11 15:23:23', 'Approved'),
('A003', 'U004', 'C001', 'USB-C Fast Charger', 'Original 65W USB-C power brick.', 'Good', 'uploads/assets/charger.jpg', 'Unavailable', 'Removed', '2026-07-27 07:01:16', '2026-08-11 15:23:23', 'Pending'),
('A004', 'U002', 'C001', 'Portable HDMI adapter', 'Compact HDMI adapter for presentations and daily use.', 'Good', 'assets/managerfile/ast004_1786360473.jpg', 'Available', 'Removed', '2026-08-10 11:12:08', '2026-08-11 15:23:23', 'Pending'),
('A005', 'U004', 'C001', 'Wireless Keyboard', 'Compact wireless keyboard suitable for study and office use.', 'Good', 'assets/managerfile/mouse.jpg', 'Unavailable', 'Removed', '2026-08-10 15:26:22', '2026-08-11 15:23:23', 'Pending'),
('A006', 'U005', 'C002', 'Data Structures Textbook', 'Reference textbook covering data structures and algorithms.', 'Like New', 'assets/managerfile/book.jpg', 'Unavailable', 'Draft', '2026-08-10 15:26:22', '2026-08-11 15:23:23', 'Pending'),
('A007', 'U004', 'C001', 'USB-C Charging Cable', 'One metre USB-C charging cable in good working condition.', 'Good', 'assets/managerfile/charger.jpg', 'Unavailable', 'Ready for Verification', '2026-08-10 15:26:22', '2026-08-11 15:23:23', 'Pending'),
('A008', 'U005', 'C002', 'Scientific Calculator', 'Scientific calculator suitable for university mathematics subjects.', 'Good', 'assets/managerfile/mouse.jpg', 'Unavailable', 'Pending Review', '2026-08-10 15:26:22', '2026-08-11 15:23:23', 'Pending');

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
('H003', 'A007', 'U002', 'Available', 'Unavailable', 'Availability updated during asset review.', '2026-08-11 12:53:57');

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
('B001', 'A002', 'U004', 'TR001', '2026-07-27 07:01:17', '2026-08-10 04:00:00', NULL, 'Borrowed');

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
('C001', 'Electronics', 'Laptops, chargers, phones, and hardware accessories', NULL, 'Active', '2026-07-27 07:01:16'),
('C002', 'Books & Stationery', 'Textbooks, revision guides, and note pads', NULL, 'Active', '2026-07-27 07:01:16'),
('C003', 'Household & Living', 'Dorm essentials, mini kettles, and lamps', NULL, 'Active', '2026-07-27 07:01:16'),
('C004', 'Sports Equipment', 'Rackets, balls, and athletic gear', NULL, 'Active', '2026-07-27 07:01:16');

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
('ET003', 'U003', 100, 'Earned', 'Exchange', 'AST001', '2026-07-27 07:01:17');

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
('M001', 'Bring Your Own Bottle', 'Use a reusable water bottle on campus for a week', 'Upload photo of bottle at APU refill station', 50, 'Active'),
('M002', 'E-Waste Recycling Drive', 'Drop off old electronics or batteries at the hub', 'Upload photo of items dropped at recycling bin', 100, 'Active'),
('M003', 'Campus Eco-Clean Walk', 'Participate in the weekly campus litter collection', 'Upload selfie at event check-in point', 150, 'Active');

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
('S001', 'U004', 'M001', 'uploads/submissions/ms1_proof.jpg', '2026-07-27 07:01:16', ''),
('S002', 'U005', 'M002', 'uploads/submissions/ms2_proof.jpg', '2026-07-27 07:01:16', 'Pending');

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
  `ecoPointsCost` int(11) NOT NULL DEFAULT 0,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `availability` enum('Available','Unavailable') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rewards`
--

INSERT INTO `rewards` (`rewardID`, `rewardName`, `rewardDescription`, `ecoPointsCost`, `quantity`, `availability`) VALUES
('R001', 'Campus Cafe RM5 Voucher', 'Redeemable at APU main cafeteria food stalls', 100, 50, 'Available'),
('R002', 'CirculAPU Tote Bag', 'Eco-friendly canvas bag with official logo', 200, 15, 'Available'),
('R003', 'APU Parking Pass (1 Day)', 'Single-day parking pass waiver for campus garage', 300, 5, 'Available');

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
('LOG026', 'U003', 'Delete', 'Verification', 'V003', 'Deleted verification record: V003', '2026-08-11 16:35:32');

-- --------------------------------------------------------

--
-- Table structure for table `tiers`
--

CREATE TABLE `tiers` (
  `tierID` varchar(10) NOT NULL,
  `tierName` varchar(50) NOT NULL,
  `tierDescription` varchar(255) NOT NULL,
  `tierIcon` varchar(255) NOT NULL,
  `minimumEcoPoints` int(11) NOT NULL DEFAULT 0,
  `minimumExchanges` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tiers`
--

INSERT INTO `tiers` (`tierID`, `tierName`, `tierDescription`, `tierIcon`, `minimumEcoPoints`, `minimumExchanges`) VALUES
('T001', 'Eco Beginner', 'Starting milestone for new sustainability participants', 'icon_tier1.png', 0, 0),
('T002', 'Green Enthusiast', 'Active participant contributing to campus recycling', 'icon_tier2.png', 100, 3),
('T003', 'Eco Champion', 'High-impact user making frequent asset exchanges', 'icon_tier3.png', 300, 10),
('T004', 'Sustainability Leader', 'Top tier user leading campus eco-initiatives', 'icon_tier4.png', 600, 20);

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
('TR001', 'A002', 'U004', '2026-07-27 07:01:17', 'Approved', 'Request granted for 14-day study period.', '2026-07-27 07:01:17', 'Hi, I need this textbook for my upcoming exam week.'),
('TR002', 'A001', 'U005', '2026-07-27 07:01:17', 'Pending', NULL, NULL, 'Can I borrow this mouse for a few days?');

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
('U004', 'Tan Miao Miao', 'tan@mail.apu.edu.my', 'password123', 'Member', 'Active', 300, '2026-07-27 07:01:16'),
('U005', 'Teo Miao Miao', 'teo@mail.apu.edu.my', 'password123', 'Member', 'Active', 85, '2026-07-27 07:01:16'),
('U006', 'Jia Qi Lim', 'limjiaqi735@gmail.com', '$2y$10$PQIKXR2iwXQnxpG0wMksXuX6Jr1FfQmWQfP7dBfBcsUQWuuPiNzFK', 'Member', 'Active', 0, '2026-07-28 05:57:29');

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
('V001', 'U004', 'U003', 'MS001', 'MissionSubmission', 'Approved', 'Clear photo proof of refill station usage.', '2026-07-27 07:03:20'),
('V002', 'U004', 'U003', 'A001', 'Asset', 'Approved', 'Asset condition verified in person.', '2026-07-27 07:03:20'),
('V004', 'U004', 'U003', 'A001', 'Asset', 'Pending', NULL, '2026-08-11 16:55:27'),
('V005', 'U004', 'U003', 'MS001', 'MissionSubmission', 'Pending', NULL, '2026-08-11 16:55:27'),
('V006', 'U004', 'U003', 'A001', 'Asset', 'Pending', NULL, '2026-08-11 16:55:27'),
('V007', 'U004', 'U003', 'MS001', 'MissionSubmission', 'Pending', NULL, '2026-08-11 16:55:27');

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
