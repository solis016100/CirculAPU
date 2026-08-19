-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 17, 2026 at 06:14 PM
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
('A006', 'U005', 'C002', 'Data Structures Textbook', 'Reference textbook covering data structures and algorithms.', 'Like New', 'assets/managerfile/book.jpg', 'Unavailable', 'Draft', '2026-08-10 15:26:22', '2026-08-11 15:23:23', 'Pending'),
('A007', 'U004', 'C001', 'USB-C Charging Cable', 'One metre USB-C charging cable in good working condition.', 'Good', 'assets/managerfile/charger.jpg', 'Unavailable', 'Removed', '2026-08-10 15:26:22', '2026-08-14 10:54:59', 'Pending'),
('A008', 'U005', 'C002', 'Scientific Calculator', 'Scientific calculator suitable for university mathematics subjects.', 'Good', 'assets/managerfile/mouse.jpg', 'Unavailable', 'Ready for Verification', '2026-08-10 15:26:22', '2026-08-13 13:00:48', 'Pending'),
('A761', 'U786', 'C781', 'RM QA2 Reserved Item 1', 'Counts toward U784 active borrowing limit.', 'Good', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-25 02:59:28', '2026-08-15 15:39:59', 'Approved'),
('A762', 'U786', 'C781', 'RM QA2 Reserved Item 2', 'Counts toward U784 active borrowing limit.', 'Good', 'assets/icons/category-electronics.png', 'Reserved', 'Published', '2026-07-26 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A763', 'U786', 'C782', 'RM QA2 Reserved Book 1', 'Counts toward U784 active borrowing limit.', 'Like New', 'assets/icons/category-books.png', 'Reserved', 'Published', '2026-07-27 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A764', 'U786', 'C782', 'RM QA2 Reserved Book 2', 'Counts toward U784 active borrowing limit.', 'Good', 'assets/icons/category-books.png', 'Reserved', 'Published', '2026-07-28 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A765', 'U786', 'C781', 'RM QA2 Reserved Item 5', 'Counts toward U784 active borrowing limity.', 'Fair', 'assets/icons/category-electronics.png', 'Reserved', 'Published', '2026-07-29 02:59:28', '2026-08-17 01:26:28', 'Approved'),
('A771', 'U786', 'C781', 'RM QA2 Unavailable Power Bank', 'Published and verified, but temporarily unavailable. Used to test approval blocking.', 'Good', 'assets/icons/category-electronics.png', 'Unavailable', 'Published', '2026-07-17 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A772', 'U786', 'C782', 'RM QA2 Same-Day Return Book', 'Available asset used to test return date equal to pickup date.', 'Like New', 'assets/icons/category-books.png', 'Available', 'Published', '2026-07-18 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A773', 'U786', 'C781', 'RM QA2 Reserved Calculator', 'Approved reservation used to test Cancel Reservation.', 'Good', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-20 02:59:28', '2026-08-15 16:12:56', 'Approved'),
('A775', 'U786', 'C782', 'RM QA2 Rejected Request Asset', 'Available asset with a historical rejected borrowing request.', 'Good', 'assets/icons/category-books.png', 'Available', 'Published', '2026-07-21 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A776', 'U786', 'C781', 'RM QA2 Cancelled Request Asset', 'Available asset with a historical cancelled reservation.', 'Good', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-07-22 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A777', 'U786', 'C781', 'RM QA2 Overdue Block Asset', 'Available asset requested by a borrower who currently has an overdue item.', 'Good', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-07-23 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A778', 'U786', 'C782', 'RM QA2 Five-Limit Block Asset', 'Available asset requested by a borrower already at the 5-active limit.', 'Like New', 'assets/icons/category-books.png', 'Available', 'Published', '2026-07-24 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A779', 'U786', 'C781', 'RM QA2 Missing Return Date Asset', 'Available asset with a Pending request missing its expected return date.', 'Good', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-07-25 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A780', 'U786', 'C782', 'RM QA2 Past Pickup Asset', 'Available asset with a Pending request whose requested pickup date has passed.', 'Good', 'assets/icons/category-books.png', 'Available', 'Published', '2026-07-26 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A781', 'U781', 'C781', 'RM QA2 Pending Wireless Keyboard', 'Pending listing ready for Resource Manager review.', 'Good', 'assets/icons/category-electronics.png', 'Unavailable', 'Ready for Verification', '2026-08-11 02:59:28', '2026-08-14 14:21:06', 'Pending'),
('A782', 'U782', 'C782', 'RM QA2 Pending Database Textbook', 'Second pending listing for Review Another testing.', 'Like New', 'assets/icons/category-books.png', 'Unavailable', 'Ready for Verification', '2026-08-12 02:59:28', '2026-08-17 01:26:55', 'Pending'),
('A783', 'U783', 'C781', 'RM QA2 Pending Desk Lamp', 'Third pending listing for review and correction testing.', 'Fair', 'assets/icons/category-electronics.png', 'Unavailable', 'Pending Review', '2026-08-13 02:59:28', NULL, 'Pending'),
('A784', 'U781', 'C782', 'RM QA2 Draft Corrected Notes', 'Draft listing representing an item returned to a member for correction.', 'Good', 'assets/icons/category-books.png', 'Unavailable', 'Draft', '2026-08-06 02:59:28', '2026-08-12 02:59:28', 'Pending'),
('A785', 'U782', 'C781', 'RM QA2 Ready Verification Monitor', 'Listing already reviewed by the Manager and waiting for Auditor verification.', 'Good', 'assets/icons/category-electronics.png', 'Unavailable', 'Ready for Verification', '2026-08-07 02:59:28', '2026-08-13 02:59:28', 'Pending'),
('A786', 'U786', 'C781', 'RM QA2 Available Wireless Mouse', 'Normal available asset for a valid Pending request.', 'Good', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-15 02:59:28', '2026-08-15 15:39:48', 'Approved'),
('A787', 'U786', 'C782', 'RM QA2 Available Reference Book', 'Available asset for testing Reject Request.', 'Like New', 'assets/icons/category-books.png', 'Reserved', 'Published', '2026-07-16 02:59:28', '2026-08-14 13:58:08', 'Approved'),
('A788', 'U786', 'C781', 'RM QA2 Low-Point Approval Charger', 'Available asset requested by a member with only 5 EcoPoints. Low points alone must not block approval.', 'Good', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-07-17 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A789', 'U786', 'C781', 'RM QA2 Pickup Reservation', 'Reserved asset used to test Confirm Pickup.', 'Good', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-27 02:59:28', '2026-08-14 13:57:59', 'Approved'),
('A790', 'U786', 'C781', 'RM QA2 Borrowed On-Time Laptop Stand', 'Currently borrowed and not overdue. Use for a normal return.', 'Good', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-28 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A791', 'U786', 'C782', 'RM QA2 Borrowed OVERDUE Textbook', 'Currently overdue. Use to test reminder and overdue EcoPoint penalty.', 'Fair', 'assets/icons/category-books.png', 'Borrowed', 'Published', '2026-07-29 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A792', 'U786', 'C781', 'RM QA2 Borrowed Due Today Adapter', 'Due today. It must NOT be marked overdue.', 'Good', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-30 02:59:28', '2026-08-14 02:59:28', 'Approved'),
('A793', 'U786', 'C781', 'RM QA2 Completed On-Time Headset', 'Previously borrowed and returned on time.', 'Good', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-06-30 02:59:28', '2026-08-03 02:59:28', 'Approved'),
('A794', 'U786', 'C782', 'RM QA2 Completed Late Book', 'Previously returned late. Includes a borrowing penalty history.', 'Fair', 'assets/icons/category-books.png', 'Available', 'Published', '2026-06-27 02:59:28', '2026-08-04 02:59:28', 'Approved'),
('A795', 'U781', 'C784', 'RM QA2 Rejected Old Listing', 'Rejected listing linked to an inactive category.', 'Poor', 'assets/icons/category-others.png', 'Unavailable', 'Rejected', '2026-06-25 02:59:28', '2026-07-05 02:59:28', 'Rejected'),
('A796', 'U782', 'C784', 'RM QA2 Removed Historical Listing', 'Soft-removed listing retained for history and category linkage.', 'Good', 'assets/icons/category-others.png', 'Unavailable', 'Removed', '2026-06-15 02:59:28', '2026-07-15 02:59:28', 'Approved'),
('A851', 'U850', 'C001', 'RETURN TEST Wireless Keyboard', 'Overdue return test sample 1.', 'Good', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-04 16:12:30', '2026-08-13 16:12:30', 'Approved'),
('A852', 'U850', 'C002', 'RETURN TEST Database Textbook', 'Overdue return test sample 2.', 'Like New', 'assets/icons/category-books.png', 'Available', 'Published', '2026-06-29 16:12:30', '2026-08-14 02:18:50', 'Approved'),
('A853', 'U850', 'C001', 'RETURN TEST USB-C Adapter', 'Overdue return test sample 3.', 'Poor', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-07-09 16:12:30', '2026-08-13 16:15:59', 'Approved'),
('A854', 'U850', 'C002', 'RETURN TEST Programming Book', 'Overdue return test sample 4.', 'Poor', 'assets/icons/category-books.png', 'Available', 'Published', '2026-06-24 16:12:30', '2026-08-13 16:15:25', 'Approved'),
('A861', 'U886', 'C881', 'RM TEST Reserved Item 1', 'Counts toward U884 active borrowing limit.', 'Good', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-24 12:35:45', '2026-08-13 15:26:14', 'Approved'),
('A862', 'U886', 'C881', 'RM TEST Reserved Item 2', 'Counts toward U884 active borrowing limit.', 'Good', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-25 12:35:45', '2026-08-13 15:36:57', 'Approved'),
('A863', 'U886', 'C882', 'RM TEST Reserved Book 1', 'Counts toward U884 active borrowing limit.', 'Like New', 'assets/icons/category-books.png', 'Reserved', 'Published', '2026-07-26 12:35:45', '2026-08-13 12:35:45', 'Approved'),
('A864', 'U886', 'C882', 'RM TEST Reserved Book 2', 'Counts toward U884 active borrowing limit.', 'Good', 'assets/icons/category-books.png', 'Reserved', 'Published', '2026-07-27 12:35:45', '2026-08-13 12:35:45', 'Approved'),
('A865', 'U886', 'C881', 'RM TEST Reserved Item 5', 'Counts toward U884 active borrowing limit.', 'Fair', 'assets/icons/category-electronics.png', 'Reserved', 'Published', '2026-07-28 12:35:45', '2026-08-13 12:35:45', 'Approved'),
('A871', 'U886', 'C881', 'RM TEST Unavailable Power Bank', 'Published and verified, but temporarily unavailable. Used to test approval blocking.', 'Good', 'assets/icons/category-electronics.png', 'Unavailable', 'Published', '2026-07-16 12:35:45', '2026-08-13 12:35:45', 'Approved'),
('A872', 'U886', 'C882', 'RM TEST Same-Day Return Book', 'Available asset used to test return date equal to pickup date.', 'Like New', 'assets/icons/category-books.png', 'Available', 'Published', '2026-07-17 12:35:45', '2026-08-13 12:35:45', 'Approved'),
('A873', 'U886', 'C881', 'RM TEST Reserved Calculator', 'Approved reservation used to test Cancel Reservation.', 'Good', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-19 12:35:45', '2026-08-13 15:36:46', 'Approved'),
('A875', 'U886', 'C882', 'RM TEST Rejected Request Asset', 'Available asset with a historical rejected borrowing request.', 'Good', 'assets/icons/category-books.png', 'Available', 'Published', '2026-07-20 12:35:45', '2026-08-13 12:35:45', 'Approved'),
('A876', 'U886', 'C881', 'RM TEST Cancelled Request Asset', 'Available asset with a historical cancelled reservation.', 'Good', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-07-21 12:35:45', '2026-08-13 12:35:45', 'Approved'),
('A877', 'U886', 'C881', 'RM TEST Overdue Block Asset', 'Available asset requested by a borrower who currently has an overdue item.', 'Good', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-07-22 12:35:45', '2026-08-13 12:35:45', 'Approved'),
('A878', 'U886', 'C882', 'RM TEST Five-Limit Block Asset', 'Available asset requested by a borrower already at the 5-active limit.', 'Like New', 'assets/icons/category-books.png', 'Available', 'Published', '2026-07-23 12:35:45', '2026-08-13 12:35:45', 'Approved'),
('A879', 'U886', 'C881', 'RM TEST Missing Return Date Asset', 'Available asset with a Pending request missing its expected return date.', 'Good', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-07-24 12:35:45', '2026-08-13 12:35:45', 'Approved'),
('A880', 'U886', 'C882', 'RM TEST Past Pickup Asset', 'Available asset with a Pending request whose requested pickup date has passed.', 'Good', 'assets/icons/category-books.png', 'Available', 'Published', '2026-07-25 12:35:45', '2026-08-13 12:35:45', 'Approved'),
('A881', 'U881', 'C881', 'RM TEST Pending Wireless Keyboard', 'Pending listing ready for Resource Manager reviews.', 'Good', 'assets/icons/category-electronics.png', 'Unavailable', 'Draft', '2026-08-10 12:35:45', '2026-08-13 13:00:30', 'Pending'),
('A882', 'U882', 'C882', 'RM TEST Pending Database Textbook', 'Second pending listing for Review Another testingsss.', 'Like New', 'assets/icons/category-books.png', 'Unavailable', 'Draft', '2026-08-11 12:35:45', '2026-08-13 13:03:39', 'Pending'),
('A883', 'U883', 'C881', 'RM TEST Pending Desk Lamp', 'Third pending listing for review and correction testing.', 'Fair', 'assets/icons/category-electronics.png', 'Unavailable', 'Ready for Verification', '2026-08-12 12:35:45', '2026-08-14 14:20:44', 'Pending'),
('A884', 'U881', 'C882', 'RM TEST Draft Corrected Notes', 'Draft listing representing an item returned to a member for correction.', 'Good', 'assets/icons/category-books.png', 'Unavailable', 'Draft', '2026-08-05 12:35:45', '2026-08-11 12:35:45', 'Pending'),
('A885', 'U882', 'C881', 'RM TEST Ready Verification Monitor', 'Listing already reviewed by the Manager and waiting for Auditor verification.', 'Good', 'assets/icons/category-electronics.png', 'Unavailable', 'Ready for Verification', '2026-08-06 12:35:45', '2026-08-12 12:35:45', 'Pending'),
('A886', 'U886', 'C881', 'RM TEST Available Wireless Mouse', 'Normal available asset for a valid Pending request.', 'Good', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-14 12:35:45', '2026-08-13 15:25:14', 'Approved'),
('A887', 'U886', 'C882', 'RM TEST Available Reference Book', 'Available asset for testing Reject Request.', 'Like New', 'assets/icons/category-books.png', 'Borrowed', 'Published', '2026-07-15 12:35:45', '2026-08-13 15:36:54', 'Approved'),
('A888', 'U886', 'C881', 'RM TEST Low-Point Approval Charger', 'Available asset requested by a member with only 5 EcoPoints. Low points alone must not block approval.', 'Good', 'assets/icons/category-electronics.png', 'Reserved', 'Published', '2026-07-16 12:35:45', '2026-08-13 15:26:02', 'Approved'),
('A889', 'U886', 'C881', 'RM TEST Pickup Reservation', 'Reserved asset used to test Confirm Pickup.', 'Good', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-26 12:35:45', '2026-08-13 15:25:08', 'Approved'),
('A890', 'U886', 'C881', 'RM TEST Borrowed On-Time Laptop Stand', 'Currently borrowed and not overdue. Use for a normal return.', 'Fair', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-07-27 12:35:45', '2026-08-13 15:53:58', 'Approved'),
('A891', 'U886', 'C882', 'RM TEST Borrowed OVERDUE Textbook', 'Currently overdue. Use to test reminder and overdue EcoPoint penalty.', 'Fair', 'assets/icons/category-books.png', 'Available', 'Published', '2026-07-28 12:35:45', '2026-08-13 15:57:32', 'Approved'),
('A892', 'U886', 'C881', 'RM TEST Borrowed Due Today Adapter', 'Due today. It must NOT be marked overdue.', 'Poor', 'assets/icons/category-electronics.png', 'Borrowed', 'Published', '2026-07-29 12:35:45', '2026-08-13 16:08:11', 'Approved'),
('A893', 'U886', 'C881', 'RM TEST Completed On-Time Headset', 'Previously borrowed and returned on time.', 'Good', 'assets/icons/category-electronics.png', 'Available', 'Published', '2026-06-29 12:35:45', '2026-08-02 12:35:45', 'Approved'),
('A894', 'U886', 'C882', 'RM TEST Completed Late Book', 'Previously returned late. Includes a borrowing penalty history.', 'Fair', 'assets/icons/category-books.png', 'Available', 'Published', '2026-06-26 12:35:45', '2026-08-03 12:35:45', 'Approved'),
('A895', 'U881', 'C884', 'RM TEST Rejected Old Listing', 'Rejected listing linked to an inactive category.', 'Poor', 'assets/icons/category-others.png', 'Unavailable', 'Rejected', '2026-06-24 12:35:45', '2026-07-04 12:35:45', 'Rejected'),
('A896', 'U882', 'C884', 'RM TEST Removed Historical Listing', 'Soft-removed listing retained for history and category linkage.', 'Good', 'assets/icons/category-others.png', 'Unavailable', 'Removed', '2026-06-14 12:35:45', '2026-07-14 12:35:45', 'Approved'),
('A901', 'U901', 'C001', 'Test Wireless Mouse', 'Future reservation more than 7 days away.', 'Good', NULL, 'Available', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A902', 'U901', 'C001', 'Test USB-C Hub', 'Pickup is within 7 days.', 'Good', NULL, 'Available', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A903', 'U901', 'C002', 'Test Algorithms Book', 'Used to test 7-day schedule conflict.', 'Like New', NULL, 'Available', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A905', 'U901', 'C001', 'Overdue Holder Asset', 'Currently held overdue by U902.', 'Good', NULL, 'Borrowed', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A906', 'U901', 'C002', 'Overdue Request Test Asset', 'New request by a requester who has an overdue asset.', 'Good', NULL, 'Available', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A910', 'U901', 'C001', 'Limit Asset 1', 'Active reservation 1.', 'Good', NULL, 'Available', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A911', 'U901', 'C001', 'Limit Asset 2', 'Active reservation 2.', 'Good', NULL, 'Available', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A912', 'U901', 'C001', 'Limit Asset 3', 'Active reservation 3.', 'Good', NULL, 'Available', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A913', 'U901', 'C002', 'Limit Asset 4', 'Active reservation 4.', 'Good', NULL, 'Available', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A914', 'U901', 'C002', 'Limit Asset 5', 'Active reservation 5.', 'Good', NULL, 'Available', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A915', 'U901', 'C001', 'Limit Request Target', 'Pending request after five active reservations.', 'Good', NULL, 'Available', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A916', 'U901', 'C001', 'Operationally Unavailable Asset', 'Used to test unavailable asset rejection.', 'Poor', NULL, 'Unavailable', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A917', 'U901', 'C002', 'Invalid Date Test Asset', 'Used to test return date before pickup date.', 'Good', NULL, 'Available', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A918', 'U901', 'C001', 'Pickup Block Test Asset', 'Still with a previous borrower while a future reservation is ready.', 'Good', NULL, 'Borrowed', 'Published', '2026-08-15 15:39:26', NULL, 'Approved'),
('A920', 'U901', 'C002', 'Return To Reservation Test Asset', 'Overdue current borrower with upcoming protected reservation.', 'Good', NULL, 'Borrowed', 'Published', '2026-08-15 15:39:26', NULL, 'Approved');

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
('H781', 'A789', 'U002', 'Available', 'Reserved', '[RM QA2] Approved reservation TR784.', '2026-08-11 02:59:28'),
('H782', 'A790', 'U002', 'Reserved', 'Borrowed', '[RM QA2] Pickup confirmed for TR785.', '2026-08-12 02:59:28'),
('H783', 'A791', 'U002', 'Reserved', 'Borrowed', '[RM QA2] Pickup confirmed for TR786.', '2026-08-04 02:59:28'),
('H784', 'A792', 'U002', 'Reserved', 'Borrowed', '[RM QA2] Pickup confirmed for TR787.', '2026-08-11 02:59:28'),
('H785', 'A793', 'U002', 'Borrowed', 'Available', '[RM QA2] On-time return completed for TR788.', '2026-08-03 02:59:28'),
('H786', 'A794', 'U002', 'Borrowed', 'Available', '[RM QA2] Late return completed for TR789.', '2026-08-04 02:59:28'),
('H881', 'A889', 'U002', 'Available', 'Reserved', '[RM TEST] Approved reservation TR884.', '2026-08-10 12:35:45'),
('H882', 'A890', 'U002', 'Reserved', 'Borrowed', '[RM TEST] Pickup confirmed for TR885.', '2026-08-11 12:35:45'),
('H883', 'A891', 'U002', 'Reserved', 'Borrowed', '[RM TEST] Pickup confirmed for TR886.', '2026-08-03 12:35:45'),
('H884', 'A892', 'U002', 'Reserved', 'Borrowed', '[RM TEST] Pickup confirmed for TR887.', '2026-08-10 12:35:45'),
('H885', 'A893', 'U002', 'Borrowed', 'Available', '[RM TEST] On-time return completed for TR888.', '2026-08-02 12:35:45'),
('H886', 'A894', 'U002', 'Borrowed', 'Available', '[RM TEST] Late return completed for TR889.', '2026-08-03 12:35:45'),
('H887', 'A886', 'U002', 'Available', 'Reserved', 'Approved and reserved for request TR881', '2026-08-13 15:15:47'),
('H888', 'A887', 'U002', 'Available', 'Reserved', 'Approved and reserved for request TR882', '2026-08-13 15:15:59'),
('H889', 'A889', 'U002', 'Reserved', 'Borrowed', 'Asset picked up for TR884', '2026-08-13 15:25:08'),
('H890', 'A886', 'U002', 'Reserved', 'Borrowed', 'Asset picked up for TR881', '2026-08-13 15:25:14'),
('H891', 'A888', 'U002', 'Available', 'Reserved', 'Approved and reserved for request TR883', '2026-08-13 15:26:02'),
('H892', 'A861', 'U002', 'Reserved', 'Borrowed', 'Asset picked up for TR861', '2026-08-13 15:26:14'),
('H893', 'A873', 'U002', 'Reserved', 'Borrowed', 'Asset picked up for TR896', '2026-08-13 15:36:46'),
('H894', 'A887', 'U002', 'Reserved', 'Borrowed', 'Asset picked up for TR882', '2026-08-13 15:36:54'),
('H895', 'A862', 'U002', 'Reserved', 'Borrowed', 'Asset picked up for TR862', '2026-08-13 15:36:57'),
('H896', 'A002', 'U002', 'Borrowed', 'Available', 'Return inspection completed for TR001', '2026-08-13 15:48:20'),
('H897', 'A890', 'U002', 'Borrowed', 'Available', 'Return inspection completed for TR885', '2026-08-13 15:53:58'),
('H898', 'A891', 'U002', 'Borrowed', 'Available', 'Return inspection completed for TR886', '2026-08-13 15:57:32'),
('H899', 'A892', 'U002', 'Borrowed', 'Unavailable', 'Return inspection completed for TR887', '2026-08-13 15:57:53'),
('H900', 'A854', 'U002', 'Borrowed', 'Available', 'Return inspection completed for TR854', '2026-08-13 16:15:25'),
('H901', 'A853', 'U002', 'Borrowed', 'Available', 'Return inspection completed for TR853', '2026-08-13 16:15:59'),
('H902', 'A852', 'U002', 'Borrowed', 'Available', 'Return inspection completed for TR852', '2026-08-14 02:18:50'),
('H903', 'A786', 'U002', 'Available', 'Reserved', 'Approved and reserved for request TR781', '2026-08-14 03:01:21'),
('H904', 'A789', 'U002', 'Reserved', 'Borrowed', 'Asset picked up for TR784', '2026-08-14 13:57:59'),
('H905', 'A787', 'U002', 'Available', 'Reserved', 'Approved and reserved for request TR782', '2026-08-14 13:58:08'),
('H906', 'A786', 'U002', 'Reserved', 'Borrowed', 'Asset picked up for TR781', '2026-08-15 15:39:48'),
('H907', 'A761', 'U002', 'Reserved', 'Borrowed', 'Asset picked up for TR761', '2026-08-15 15:39:59'),
('H908', 'A773', 'U002', 'Reserved', 'Borrowed', 'Asset picked up for TR796', '2026-08-15 16:12:56');

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
('B761', 'A761', 'U784', 'TR761', '2026-08-16 02:59:28', '2026-08-23 10:59:28', NULL, 'Borrowed'),
('B762', 'A762', 'U784', 'TR762', '2026-08-17 02:59:28', '2026-08-24 10:59:28', NULL, 'Approved'),
('B763', 'A763', 'U784', 'TR763', '2026-08-18 02:59:28', '2026-08-25 10:59:28', NULL, 'Approved'),
('B764', 'A764', 'U784', 'TR764', '2026-08-19 02:59:28', '2026-08-26 10:59:28', NULL, 'Approved'),
('B765', 'A765', 'U784', 'TR765', '2026-08-20 02:59:28', '2026-08-27 10:59:28', NULL, 'Approved'),
('B781', 'A786', 'U781', 'TR781', '2026-08-15 02:59:28', '2026-08-22 10:59:28', NULL, 'Borrowed'),
('B782', 'A787', 'U782', 'TR782', '2026-08-16 02:59:28', '2026-08-23 10:59:28', NULL, 'Approved'),
('B783', 'A788', 'U785', 'TR783', '2026-08-17 02:59:28', '2026-08-24 10:59:28', NULL, 'Pending'),
('B784', 'A789', 'U781', 'TR784', '2026-08-15 02:59:28', '2026-08-22 10:59:28', NULL, 'Borrowed'),
('B785', 'A790', 'U782', 'TR785', '2026-08-12 02:59:28', '2026-08-19 10:59:28', NULL, 'Borrowed'),
('B786', 'A791', 'U783', 'TR786', '2026-08-04 02:59:28', '2026-08-12 10:59:28', NULL, 'Borrowed'),
('B787', 'A792', 'U781', 'TR787', '2026-08-11 02:59:28', '2026-08-14 23:59:59', NULL, 'Borrowed'),
('B788', 'A793', 'U782', 'TR788', '2026-07-25 02:59:28', '2026-08-04 10:59:28', '2026-08-03 10:59:28', 'Completed'),
('B789', 'A794', 'U781', 'TR789', '2026-07-25 02:59:28', '2026-08-02 10:59:28', '2026-08-04 10:59:28', 'Completed'),
('B790', 'A775', 'U782', 'TR790', '2026-08-15 02:59:28', '2026-08-22 10:59:28', NULL, 'Cancelled'),
('B791', 'A776', 'U781', 'TR791', '2026-08-15 02:59:28', '2026-08-22 10:59:28', NULL, 'Cancelled'),
('B792', 'A777', 'U783', 'TR792', '2026-08-16 02:59:28', '2026-08-23 10:59:28', NULL, 'Pending'),
('B793', 'A778', 'U784', 'TR793', '2026-08-16 02:59:28', '2026-08-23 10:59:28', NULL, 'Pending'),
('B794', 'A779', 'U781', 'TR794', '2026-08-16 02:59:28', '0000-00-00 00:00:00', NULL, 'Pending'),
('B795', 'A780', 'U782', 'TR795', '2026-08-13 02:59:28', '2026-08-19 10:59:28', NULL, 'Pending'),
('B796', 'A773', 'U782', 'TR796', '2026-08-16 02:59:28', '2026-08-23 10:59:28', NULL, 'Borrowed'),
('B797', 'A772', 'U785', 'TR797', '2026-08-16 02:59:28', '2026-08-16 10:59:28', NULL, 'Pending'),
('B798', 'A771', 'U785', 'TR798', '2026-08-16 02:59:28', '2026-08-23 10:59:28', NULL, 'Pending'),
('B851', 'A851', 'U851', 'TR851', '2026-08-03 16:12:30', '2026-08-11 00:12:30', NULL, 'Borrowed'),
('B852', 'A852', 'U852', 'TR852', '2026-08-01 16:12:30', '2026-08-09 00:12:30', '2026-08-14 10:18:50', 'Completed'),
('B853', 'A853', 'U853', 'TR853', '2026-07-30 16:12:30', '2026-08-07 00:12:30', '2026-08-14 00:15:59', 'Completed'),
('B854', 'A854', 'U854', 'TR854', '2026-07-27 16:12:30', '2026-08-04 00:12:30', '2026-08-14 00:15:25', 'Completed'),
('B861', 'A861', 'U884', 'TR861', '2026-08-15 12:35:45', '2026-08-22 20:35:45', NULL, 'Borrowed'),
('B862', 'A862', 'U884', 'TR862', '2026-08-16 12:35:45', '2026-08-23 20:35:45', NULL, 'Borrowed'),
('B863', 'A863', 'U884', 'TR863', '2026-08-17 12:35:45', '2026-08-24 20:35:45', NULL, 'Approved'),
('B864', 'A864', 'U884', 'TR864', '2026-08-18 12:35:45', '2026-08-25 20:35:45', NULL, 'Approved'),
('B865', 'A865', 'U884', 'TR865', '2026-08-19 12:35:45', '2026-08-26 20:35:45', NULL, 'Approved'),
('B881', 'A886', 'U881', 'TR881', '2026-08-14 12:35:45', '2026-08-21 20:35:45', NULL, 'Borrowed'),
('B882', 'A887', 'U882', 'TR882', '2026-08-15 12:35:45', '2026-08-22 20:35:45', NULL, 'Borrowed'),
('B883', 'A888', 'U885', 'TR883', '2026-08-16 12:35:45', '2026-08-23 20:35:45', NULL, 'Approved'),
('B884', 'A889', 'U881', 'TR884', '2026-08-14 12:35:45', '2026-08-21 20:35:45', NULL, 'Borrowed'),
('B885', 'A890', 'U882', 'TR885', '2026-08-11 12:35:45', '2026-08-18 20:35:45', '2026-08-13 23:53:58', 'Completed'),
('B886', 'A891', 'U883', 'TR886', '2026-08-03 12:35:45', '2026-08-11 20:35:45', '2026-08-13 23:57:32', 'Completed'),
('B887', 'A892', 'U881', 'TR887', '2026-08-10 12:35:45', '2026-08-13 00:00:00', NULL, 'Overdue'),
('B888', 'A893', 'U882', 'TR888', '2026-07-24 12:35:45', '2026-08-03 20:35:45', '2026-08-02 20:35:45', 'Completed'),
('B889', 'A894', 'U881', 'TR889', '2026-07-24 12:35:45', '2026-08-01 20:35:45', '2026-08-03 20:35:45', 'Completed'),
('B890', 'A875', 'U882', 'TR890', '2026-08-14 12:35:45', '2026-08-21 20:35:45', NULL, 'Cancelled'),
('B891', 'A876', 'U881', 'TR891', '2026-08-14 12:35:45', '2026-08-21 20:35:45', NULL, 'Cancelled'),
('B892', 'A877', 'U883', 'TR892', '2026-08-15 12:35:45', '2026-08-22 20:35:45', NULL, 'Cancelled'),
('B893', 'A878', 'U884', 'TR893', '2026-08-15 12:35:45', '2026-08-22 20:35:45', NULL, 'Cancelled'),
('B894', 'A879', 'U881', 'TR894', '2026-08-15 12:35:45', '0000-00-00 00:00:00', NULL, 'Cancelled'),
('B895', 'A880', 'U882', 'TR895', '2026-08-12 12:35:45', '2026-08-18 20:35:45', NULL, 'Cancelled'),
('B896', 'A873', 'U882', 'TR896', '2026-08-15 12:35:45', '2026-08-22 20:35:45', NULL, 'Borrowed'),
('B897', 'A872', 'U885', 'TR897', '2026-08-15 12:35:45', '2026-08-15 20:35:45', NULL, 'Pending'),
('B898', 'A871', 'U885', 'TR898', '2026-08-15 12:35:45', '2026-08-22 20:35:45', NULL, 'Cancelled'),
('B901', 'A901', 'U901', 'TR901', '2026-08-28 16:00:00', '2026-09-05 00:00:00', NULL, 'Pending'),
('B902', 'A902', 'U901', 'TR902', '2026-08-19 16:00:00', '2026-08-27 00:00:00', NULL, 'Pending'),
('B903', 'A903', 'U902', 'TR903', '2026-08-24 16:00:00', '2026-08-30 00:00:00', NULL, 'Approved'),
('B904', 'A903', 'U901', 'TR904', '2026-09-01 16:00:00', '2026-09-07 00:00:00', NULL, 'Pending'),
('B905', 'A905', 'U902', 'TR905', '2026-08-04 16:00:00', '2026-08-14 00:00:00', NULL, 'Borrowed'),
('B906', 'A906', 'U902', 'TR906', '2026-08-28 16:00:00', '2026-09-04 00:00:00', NULL, 'Pending'),
('B910', 'A910', 'U903', 'TR910', '2026-09-13 16:00:00', '2026-09-19 00:00:00', NULL, 'Approved'),
('B911', 'A911', 'U903', 'TR911', '2026-09-14 16:00:00', '2026-09-20 00:00:00', NULL, 'Approved'),
('B912', 'A912', 'U903', 'TR912', '2026-09-15 16:00:00', '2026-09-21 00:00:00', NULL, 'Approved'),
('B913', 'A913', 'U903', 'TR913', '2026-09-16 16:00:00', '2026-09-22 00:00:00', NULL, 'Approved'),
('B914', 'A914', 'U903', 'TR914', '2026-09-17 16:00:00', '2026-09-23 00:00:00', NULL, 'Approved'),
('B915', 'A915', 'U903', 'TR915', '2026-09-03 16:00:00', '2026-09-11 00:00:00', NULL, 'Pending'),
('B916', 'A916', 'U901', 'TR916', '2026-08-28 16:00:00', '2026-09-04 00:00:00', NULL, 'Pending'),
('B917', 'A917', 'U901', 'TR917', '2026-08-24 16:00:00', '2026-08-20 00:00:00', NULL, 'Pending'),
('B918', 'A918', 'U902', 'TR918', '2026-08-10 16:00:00', '2026-08-16 00:00:00', NULL, 'Borrowed'),
('B919', 'A918', 'U901', 'TR919', '2026-08-19 16:00:00', '2026-08-27 00:00:00', NULL, 'Cancelled'),
('B920', 'A920', 'U902', 'TR920', '2026-08-02 16:00:00', '2026-08-12 00:00:00', NULL, 'Borrowed'),
('B921', 'A920', 'U901', 'TR921', '2026-08-19 16:00:00', '2026-08-27 00:00:00', NULL, 'Approved');

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
('C004', 'Sports Equipment', 'Rackets, balls, and athletic gear', NULL, 'Active', '2026-07-27 07:01:16'),
('C781', 'RM QA2 - Electronics', 'Temporary electronics category for Resource Manager testing.', 'assets/icons/category-electronics.png', 'Active', '2026-07-05 02:59:28'),
('C782', 'RM QA2 - Books', 'Temporary books category for Resource Manager testing.', 'assets/icons/category-books.png', 'Active', '2026-07-10 02:59:28'),
('C783', 'RM QA2 - Empty Deletable', 'No assets are linked to this category. It may be permanently deleted.', 'assets/icons/category-others.png', 'Active', '2026-08-09 02:59:28'),
('C784', 'RM QA2 - Inactive Linked', 'Inactive category with linked historical assets.', 'assets/icons/category-others.png', 'Inactive', '2026-06-25 02:59:28'),
('C881', 'RM TEST - Electronics', 'Temporary electronics category for Resource Manager testing.', 'assets/icons/category-electronics.png', 'Active', '2026-07-04 12:35:45'),
('C882', 'RM TEST - Books', 'Temporary books category for Resource Manager testing.', 'assets/icons/category-books.png', 'Active', '2026-07-09 12:35:45'),
('C883', 'RM TEST - Empty Deletable', 'No assets are linked to this category. It may be permanently delete.', 'assets/icons/category-others.png', 'Active', '2026-08-08 12:35:45'),
('C884', 'RM TEST - Inactive Linked', 'Inactive category with linked historical assets.', 'assets/icons/category-others.png', 'Active', '2026-06-24 12:35:45'),
('C885', 'book', 'this book is for everyone', 'assets/icons/category-books.png', 'Active', '2026-08-13 14:51:42'),
('C886', 'sport', 'sportsss', 'assets/icons/category-sports.png', 'Active', '2026-08-13 14:57:53'),
('C887', 'music', '', 'assets/icons/category-music.png', 'Active', '2026-08-14 14:21:20');

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
('ET781', 'U781', -15, 'Spent', 'BorrowingPenalty', 'TR789', '2026-08-04 02:59:28'),
('ET782', 'U781', 50, 'Earned', 'Mission', 'RMQA2', '2026-07-15 02:59:28'),
('ET783', 'U782', 25, 'Earned', 'Mission', 'RMQA2', '2026-07-20 02:59:28'),
('ET881', 'U881', -15, 'Spent', 'BorrowingPenalty', 'TR889', '2026-08-03 12:35:45'),
('ET882', 'U881', 50, 'Earned', 'Mission', 'RMTEST', '2026-07-14 12:35:45'),
('ET883', 'U882', 25, 'Earned', 'Mission', 'RMTEST', '2026-07-19 12:35:45'),
('ET884', 'U004', -5, 'Spent', 'BorrowingPenalty', 'TR001', '2026-08-13 15:48:20'),
('ET885', 'U882', -5, 'Spent', 'BorrowingPenalty', 'TR885', '2026-08-13 15:53:58'),
('ET886', 'U883', -100, 'Spent', 'BorrowingPenalty', 'TR886', '2026-08-13 15:57:32'),
('ET887', 'U881', -100, 'Spent', 'BorrowingPenalty', 'TR887', '2026-08-13 15:57:53'),
('ET888', 'U853', -5, 'Spent', 'BorrowingPenalty', 'TR853', '2026-08-13 16:15:59');

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
('N781', 'U002', '[RM QA2] Pending Listing Reminder', 'Three RM QA2 asset listings are waiting for Resource Manager review.', 'Unread', '2026-08-14 00:59:28'),
('N782', 'U002', '[RM QA2] Transaction Activity', 'RM QA2 transaction data is available for filter, status and report testing.', 'Read', '2026-08-13 02:59:28'),
('N783', 'U002', '[RM QA2] Overdue Return Alert', 'TR786 is an RM QA2 overdue borrowing. Test reminder and Return Inspection from Manage Returns.', 'Unread', '2026-08-14 02:29:28'),
('N881', 'U002', '[RM TEST] Pending Listing Reminder', 'Three RM TEST asset listings are waiting for Resource Manager review.', 'Read', '2026-08-13 10:35:45'),
('N882', 'U002', '[RM TEST] Transaction Activity', 'RM TEST transaction data is available for filter, status and report testing.', 'Read', '2026-08-12 12:35:45'),
('N883', 'U002', '[RM TEST] Overdue Return Alert', 'TR886 is an RM TEST overdue borrowing. Test reminder and Return Inspection from Manage Returns.', 'Read', '2026-08-13 12:05:45'),
('N884', 'U881', 'Asset Listing Returned', 'Asset A881 was returned for correction. Reason: too shit', 'Unread', '2026-08-13 13:00:30'),
('N885', 'U005', 'Asset Sent for Verification', 'Asset A008 has been sent for Sustainability Auditor verification.', 'Unread', '2026-08-13 13:00:48'),
('N886', 'U882', 'Asset Listing Returned', 'Asset A882 was returned for correction. Reason: noob', 'Unread', '2026-08-13 13:03:39'),
('N887', 'U883', 'Asset Sent for Verification', 'Asset A883 has been sent for Sustainability Auditor verification.', 'Unread', '2026-08-13 13:03:55'),
('N888', 'U005', 'Borrowing Request Rejected', 'Your request TR002 for Dell Wireless Mouse was rejected. Reason: dont have pickup date and request date', 'Unread', '2026-08-13 15:12:34'),
('N889', 'U881', 'Request Approved & Asset Reserved', 'Your request TR881 for RM TEST Available Wireless Mouse was approved and the asset is reserved for you. Pickup: 14 Aug 2026. Return by: 21 Aug 2026.', 'Unread', '2026-08-13 15:15:47'),
('N890', 'U882', 'Request Approved & Asset Reserved', 'Your request TR882 for RM TEST Available Reference Book was approved and the asset is reserved for you. Pickup: 15 Aug 2026. Return by: 22 Aug 2026.', 'Unread', '2026-08-13 15:15:59'),
('N891', 'U883', 'Borrowing Request Rejected', 'Your request TR892 for RM TEST Overdue Block Asset was rejected. Reason: overdue unreturend asset', 'Unread', '2026-08-13 15:16:26'),
('N892', 'U881', 'Pickup Confirmed', 'Pickup for RM TEST Pickup Reservation was confirmed. Your borrowing has started. Return by: 21 Aug 2026.', 'Unread', '2026-08-13 15:25:08'),
('N893', 'U881', 'Pickup Confirmed', 'Pickup for RM TEST Available Wireless Mouse was confirmed. Your borrowing has started. Return by: 21 Aug 2026.', 'Unread', '2026-08-13 15:25:14'),
('N894', 'U885', 'Request Approved & Asset Reserved', 'Your request TR883 for RM TEST Low-Point Approval Charger was approved and the asset is reserved for you. Pickup: 16 Aug 2026. Return by: 23 Aug 2026.', 'Unread', '2026-08-13 15:26:02'),
('N895', 'U884', 'Pickup Confirmed', 'Pickup for RM TEST Reserved Item 1 was confirmed. Your borrowing has started. Return by: 22 Aug 2026.', 'Unread', '2026-08-13 15:26:14'),
('N896', 'U882', 'Borrowing Request Rejected', 'Your request TR895 for RM TEST Past Pickup Asset was rejected. Reason: pickupdate laready passed', 'Unread', '2026-08-13 15:27:24'),
('N897', 'U884', 'Borrowing Request Rejected', 'Your request TR893 for RM TEST Five-Limit Block Asset was rejected. Reason: you have more than 5', 'Unread', '2026-08-13 15:36:10'),
('N898', 'U885', 'Borrowing Request Rejected', 'Your request TR898 for RM TEST Unavailable Power Bank was rejected. Reason: not available', 'Unread', '2026-08-13 15:36:32'),
('N899', 'U882', 'Pickup Confirmed', 'Pickup for RM TEST Reserved Calculator was confirmed. Your borrowing has started. Return by: 22 Aug 2026.', 'Unread', '2026-08-13 15:36:46'),
('N900', 'U882', 'Pickup Confirmed', 'Pickup for RM TEST Available Reference Book was confirmed. Your borrowing has started. Return by: 22 Aug 2026.', 'Unread', '2026-08-13 15:36:54'),
('N901', 'U884', 'Pickup Confirmed', 'Pickup for RM TEST Reserved Item 2 was confirmed. Your borrowing has started. Return by: 23 Aug 2026.', 'Unread', '2026-08-13 15:36:57'),
('N902', 'U881', 'Borrowing Request Rejected', 'Your request TR894 for RM TEST Missing Return Date Asset was rejected. Reason: renew the date', 'Unread', '2026-08-13 15:37:25'),
('N903', 'U004', 'Asset Return Completed', 'Return for Java Programming Textbook completed. Returned condition: Good. Asset status: Available. 5 EcoPoints were deducted. New balance: 295. Reason: damange a little', 'Unread', '2026-08-13 15:48:20'),
('N904', 'U882', 'Asset Return Completed', 'Return for RM TEST Borrowed On-Time Laptop Stand completed. Returned condition: Fair. Asset status: Available. 5 EcoPoints were deducted. New balance: 175. Reason: abit cracky', 'Unread', '2026-08-13 15:53:58'),
('N905', 'U883', 'Overdue Asset Return Reminder', 'Transaction TR886: Your borrowed asset RM TEST Borrowed OVERDUE Textbook was due on 11 Aug 2026. Please return the asset as soon as possible.', 'Unread', '2026-08-13 15:57:07'),
('N906', 'U883', 'Asset Return Completed', 'Return for RM TEST Borrowed OVERDUE Textbook completed. Returned condition: Fair. Asset status: Available. 100 EcoPoints were deducted. New balance: -5. Reason: late', 'Unread', '2026-08-13 15:57:32'),
('N907', 'U881', 'Asset Return Completed', 'Return for RM TEST Borrowed Due Today Adapter completed. Returned condition: Poor. Asset status: Unavailable. 100 EcoPoints were deducted. New balance: 185. Reason: damaged', 'Unread', '2026-08-13 15:57:53'),
('N908', 'U854', 'Overdue Asset Return Reminder', 'Transaction TR854: Your borrowed asset RETURN TEST Programming Book was due on 04 Aug 2026. Please return the asset as soon as possible.', 'Unread', '2026-08-13 16:12:43'),
('N909', 'U853', 'Overdue Asset Return Reminder', 'Transaction TR853: Your borrowed asset RETURN TEST USB-C Adapter was due on 07 Aug 2026. Please return the asset as soon as possible.', 'Unread', '2026-08-13 16:12:51'),
('N910', 'U852', 'Overdue Asset Return Reminder', 'Transaction TR852: Your borrowed asset RETURN TEST Database Textbook was due on 09 Aug 2026. Please return the asset as soon as possible.', 'Unread', '2026-08-13 16:12:57'),
('N911', 'U854', 'Asset Return Completed', 'Return for RETURN TEST Programming Book completed. Returned condition: Poor. Asset status: Available.', 'Unread', '2026-08-13 16:15:25'),
('N912', 'U853', 'Asset Return Completed', 'Return for RETURN TEST USB-C Adapter completed. Returned condition: Poor. Asset status: Available. 5 EcoPoints were deducted. New balance: 105. Reason: noob', 'Unread', '2026-08-13 16:15:59'),
('N913', 'U852', 'Asset Return Completed', 'Return for RETURN TEST Database Textbook completed. Returned condition: Like New. Asset status: Available.', 'Unread', '2026-08-14 02:18:50'),
('N914', 'U781', 'Request Approved & Asset Reserved', 'Your request TR781 for RM QA2 Available Wireless Mouse was approved and the asset is reserved for you. Pickup: 15 Aug 2026. Return by: 22 Aug 2026.', 'Unread', '2026-08-14 03:01:21'),
('N915', 'U781', 'Pickup Confirmed', 'Pickup for RM QA2 Pickup Reservation was confirmed. Your borrowing has started. Return by: 22 Aug 2026.', 'Unread', '2026-08-14 13:57:59'),
('N916', 'U782', 'Request Approved & Asset Reserved', 'Your request TR782 for RM QA2 Available Reference Book was approved and the asset is reserved for you. Pickup: 16 Aug 2026. Return by: 23 Aug 2026.', 'Unread', '2026-08-14 13:58:08'),
('N917', 'U781', 'Asset Sent for Verification', 'Asset A781 has been sent for Sustainability Auditor verification.', 'Unread', '2026-08-14 14:21:06'),
('N918', 'U781', 'Pickup Confirmed', 'Pickup for RM QA2 Available Wireless Mouse was confirmed. Your borrowing has started. Return by: 22 Aug 2026.', 'Unread', '2026-08-15 15:39:48'),
('N919', 'U784', 'Pickup Confirmed', 'Pickup for RM QA2 Reserved Item 1 was confirmed. Your borrowing has started. Return by: 23 Aug 2026.', 'Unread', '2026-08-15 15:39:59'),
('N920', 'U901', 'Reservation Cancelled', 'The reservation for transaction TR919 was cancelled before pickup. Reason: no avaialble', 'Unread', '2026-08-15 15:54:16'),
('N921', 'U782', 'Pickup Confirmed', 'Pickup for RM QA2 Reserved Calculator was confirmed. Your borrowing has started. Return by: 23 Aug 2026.', 'Unread', '2026-08-15 16:12:56'),
('N922', 'U782', 'Asset Sent for Verification', 'Asset A782 has been sent for Sustainability Auditor verification.', 'Unread', '2026-08-17 01:26:55'),
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
  `rewardImage` varchar(255) DEFAULT NULL,
  `ecoPointsCost` int(11) NOT NULL DEFAULT 0,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `availability` enum('Available','Unavailable') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rewards`
--

INSERT INTO `rewards` (`rewardID`, `rewardName`, `rewardDescription`, `rewardImage`, `ecoPointsCost`, `quantity`, `availability`) VALUES
('R001', 'Campus Cafe RM5 Voucher', 'Redeemable at APU main cafeteria food stalls', 'campus_cafe_rm5.png', 100, 50, 'Available'),
('R002', 'CirculAPU Tote Bag', 'Eco-friendly canvas bag with official logo', 'circulapu_tote_bag.png', 200, 15, 'Available'),
('R003', 'APU Parking Pass (1 Day)', 'Single-day parking pass waiver for campus garage', 'apu_parking_pass.png', 300, 5, 'Available');

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
('LOG888', 'U002', 'Submit', 'Asset', 'A885', '[RM TEST] Sent asset listing A885 for Sustainability Auditor verification.', '2026-08-11 12:35:45'),
('LOG889', 'U002', 'Create', 'Category', 'C883', '[RM TEST] Created empty test category for permanent-delete testing.', '2026-08-12 12:35:45'),
('LOG890', 'U002', 'Approve', 'Transaction', 'TR884', '[RM TEST] Approved request TR884 and reserved A889.', '2026-08-12 16:35:45'),
('LOG891', 'U002', 'Assign', 'Transaction', 'TR885', '[RM TEST] Pickup confirmed for TR885; borrowing started.', '2026-08-13 00:35:45'),
('LOG892', 'U002', 'Update', 'Transaction', 'TR886', '[RM TEST] Overdue return requires Resource Manager attention.', '2026-08-13 08:35:45'),
('LOG893', 'U002', 'Update', 'Borrowing', 'B887', '[RM TEST] Due-today borrowing should remain Borrowed, not Overdue.', '2026-08-13 11:35:45'),
('LOG894', 'U002', 'Update', 'Asset', 'A881', 'Updated asset submission during review: RM TEST Pending Wireless Keyboard', '2026-08-13 12:52:17'),
('LOG895', 'U002', 'Update', 'Asset', 'A881', 'Updated asset submission during review: RM TEST Pending Wireless Keyboard', '2026-08-13 12:52:31'),
('LOG896', 'U002', 'Update', 'Asset', 'A881', 'Updated asset submission during review: RM TEST Pending Wireless Keyboard', '2026-08-13 12:53:03'),
('LOG897', 'U002', 'Update', 'Asset', 'A881', 'Updated asset submission during review: RM TEST Pending Wireless Keyboard', '2026-08-13 12:59:26'),
('LOG898', 'U002', 'Update', 'Asset', 'A881', 'Returned A881 for correction. Reason: too shit', '2026-08-13 13:00:30'),
('LOG899', 'U002', 'Submit', 'Asset', 'A008', 'Sent asset listing for verification: Scientific Calculator', '2026-08-13 13:00:48'),
('LOG900', 'U002', 'Update', 'Asset', 'A882', 'Updated asset submission during review: RM TEST Pending Database Textbook', '2026-08-13 13:03:25'),
('LOG901', 'U002', 'Update', 'Asset', 'A882', 'Returned A882 for correction. Reason: noob', '2026-08-13 13:03:39'),
('LOG902', 'U002', 'Submit', 'Asset', 'A883', 'Sent asset listing for verification: RM TEST Pending Desk Lamp', '2026-08-13 13:03:55'),
('LOG903', 'U002', 'Update', 'Category', 'C881', 'Deactivated category: RM TEST - Electronics', '2026-08-13 14:42:28'),
('LOG904', 'U002', 'Update', 'Category', 'C881', 'Updated category: RM TEST - Electronics', '2026-08-13 14:42:40'),
('LOG905', 'U002', 'Update', 'Category', 'C884', 'Updated category: RM TEST - Inactive Linked', '2026-08-13 14:43:23'),
('LOG906', 'U002', 'Update', 'Category', 'C883', 'Updated category: RM TEST - Empty Deletable', '2026-08-13 14:45:34'),
('LOG907', 'U002', 'Update', 'Category', 'C883', 'Updated category: RM TEST - Empty Deletable', '2026-08-13 14:45:45'),
('LOG908', 'U002', 'Update', 'Category', 'C883', 'Updated category: RM TEST - Empty Deletable', '2026-08-13 14:45:50'),
('LOG909', 'U002', 'Create', 'Category', 'C885', 'Created category: book', '2026-08-13 14:51:42'),
('LOG910', 'U002', 'Create', 'Category', 'C886', 'Created category: hhh', '2026-08-13 14:51:54'),
('LOG911', 'U002', 'Update', 'Category', 'C886', 'Deactivated category: hhh', '2026-08-13 14:56:04'),
('LOG912', 'U002', 'Delete', 'Category', 'C886', 'Permanently deleted category: hhh', '2026-08-13 14:56:40'),
('LOG913', 'U002', 'Update', 'Category', 'C885', 'Deactivated category: book', '2026-08-13 14:56:59'),
('LOG914', 'U002', 'Update', 'Category', 'C885', 'Updated category: book', '2026-08-13 14:57:15'),
('LOG915', 'U002', 'Create', 'Category', 'C886', 'Created category: sport', '2026-08-13 14:57:53'),
('LOG916', 'U002', 'Update', 'Category', 'C886', 'Deactivated category: sport', '2026-08-13 14:58:24'),
('LOG917', 'U002', 'Create', 'Category', 'C887', 'Created category: pencil', '2026-08-13 14:59:27'),
('LOG918', 'U002', 'Update', 'Category', 'C886', 'Reactivated category: sport', '2026-08-13 15:01:04'),
('LOG919', 'U002', 'Delete', 'Category', 'C887', 'Permanently deleted category: pencil', '2026-08-13 15:02:11'),
('LOG920', 'U002', 'Update', 'Category', 'C886', 'Updated category: sport', '2026-08-13 15:03:01'),
('LOG921', 'U002', 'Reject', 'Transaction', 'TR002', 'Rejected borrowing request for Dell Wireless Mouse. Reason: dont have pickup date and request date', '2026-08-13 15:12:34'),
('LOG922', 'U002', 'Approve', 'Transaction', 'TR881', 'Approved borrowing request and reserved RM TEST Available Wireless Mouse', '2026-08-13 15:15:47'),
('LOG923', 'U002', 'Approve', 'Transaction', 'TR882', 'Approved borrowing request and reserved RM TEST Available Reference Book', '2026-08-13 15:15:59'),
('LOG924', 'U002', 'Reject', 'Transaction', 'TR892', 'Rejected borrowing request for RM TEST Overdue Block Asset. Reason: overdue unreturend asset', '2026-08-13 15:16:26'),
('LOG925', 'U002', 'Assign', 'Transaction', 'TR884', 'Pickup confirmed; borrowing started for RM TEST Pickup Reservation', '2026-08-13 15:25:08'),
('LOG926', 'U002', 'Assign', 'Transaction', 'TR881', 'Pickup confirmed; borrowing started for RM TEST Available Wireless Mouse', '2026-08-13 15:25:14'),
('LOG927', 'U002', 'Approve', 'Transaction', 'TR883', 'Approved borrowing request and reserved RM TEST Low-Point Approval Charger', '2026-08-13 15:26:02'),
('LOG928', 'U002', 'Assign', 'Transaction', 'TR861', 'Pickup confirmed; borrowing started for RM TEST Reserved Item 1', '2026-08-13 15:26:14'),
('LOG929', 'U002', 'Reject', 'Transaction', 'TR895', 'Rejected borrowing request for RM TEST Past Pickup Asset. Reason: pickupdate laready passed', '2026-08-13 15:27:24'),
('LOG930', 'U002', 'Reject', 'Transaction', 'TR893', 'Rejected borrowing request for RM TEST Five-Limit Block Asset. Reason: you have more than 5', '2026-08-13 15:36:10'),
('LOG931', 'U002', 'Reject', 'Transaction', 'TR898', 'Rejected borrowing request for RM TEST Unavailable Power Bank. Reason: not available', '2026-08-13 15:36:32'),
('LOG932', 'U002', 'Assign', 'Transaction', 'TR896', 'Pickup confirmed; borrowing started for RM TEST Reserved Calculator', '2026-08-13 15:36:46'),
('LOG933', 'U002', 'Assign', 'Transaction', 'TR882', 'Pickup confirmed; borrowing started for RM TEST Available Reference Book', '2026-08-13 15:36:54'),
('LOG934', 'U002', 'Assign', 'Transaction', 'TR862', 'Pickup confirmed; borrowing started for RM TEST Reserved Item 2', '2026-08-13 15:36:57'),
('LOG935', 'U002', 'Reject', 'Transaction', 'TR894', 'Rejected borrowing request for RM TEST Missing Return Date Asset. Reason: renew the date', '2026-08-13 15:37:25'),
('LOG936', 'U002', 'Update', 'Transaction', 'TR001', 'Return completed for Java Programming Textbook: Like New → Good; asset Available; deducted 5 EcoPoints (new balance: 295). Reason: damange a little; returned overdue.', '2026-08-13 15:48:20'),
('LOG937', 'U002', 'Update', 'Transaction', 'TR885', 'Return completed for RM TEST Borrowed On-Time Laptop Stand: Good → Fair; asset Available; deducted 5 EcoPoints (new balance: 175). Reason: abit cracky', '2026-08-13 15:53:58'),
('LOG938', 'U002', 'Update', 'Transaction', 'TR886', 'Return reminder sent to RM Test Chloe Overdue for overdue asset RM TEST Borrowed OVERDUE Textbook.', '2026-08-13 15:57:07'),
('LOG939', 'U002', 'Update', 'Transaction', 'TR886', 'Return completed for RM TEST Borrowed OVERDUE Textbook: Fair → Fair; asset Available; deducted 100 EcoPoints (new balance: -5). Reason: late; returned overdue.', '2026-08-13 15:57:32'),
('LOG940', 'U002', 'Update', 'Transaction', 'TR887', 'Return completed for RM TEST Borrowed Due Today Adapter: Good → Poor; asset Unavailable; deducted 100 EcoPoints (new balance: 185). Reason: damaged', '2026-08-13 15:57:53'),
('LOG941', 'U002', 'Update', 'Transaction', 'TR854', 'Return reminder sent to Return Test Dylan for overdue asset RETURN TEST Programming Book.', '2026-08-13 16:12:43'),
('LOG942', 'U002', 'Update', 'Transaction', 'TR853', 'Return reminder sent to Return Test Carmen for overdue asset RETURN TEST USB-C Adapter.', '2026-08-13 16:12:51'),
('LOG943', 'U002', 'Update', 'Transaction', 'TR852', 'Return reminder sent to Return Test Bryan for overdue asset RETURN TEST Database Textbook.', '2026-08-13 16:12:57'),
('LOG944', 'U002', 'Update', 'Transaction', 'TR854', 'Return completed for RETURN TEST Programming Book: Poor → Poor; asset Available; returned overdue.', '2026-08-13 16:15:25'),
('LOG945', 'U002', 'Update', 'Transaction', 'TR853', 'Return completed for RETURN TEST USB-C Adapter: Fair → Poor; asset Available; deducted 5 EcoPoints (new balance: 105). Reason: noob; returned overdue.', '2026-08-13 16:15:59'),
('LOG946', 'U002', 'Update', 'Transaction', 'TR852', 'Return completed for RETURN TEST Database Textbook: Like New → Like New; asset Available; returned overdue.', '2026-08-14 02:18:50'),
('LOG947', 'U002', 'Submit', 'Asset', 'A785', '[RM QA2] Sent asset listing A785 for Sustainability Auditor verification.', '2026-08-12 02:59:28'),
('LOG948', 'U002', 'Create', 'Category', 'C783', '[RM QA2] Created empty test category for permanent-delete testing.', '2026-08-13 02:59:28'),
('LOG949', 'U002', 'Approve', 'Transaction', 'TR784', '[RM QA2] Approved request TR784 and reserved A789.', '2026-08-13 06:59:28'),
('LOG950', 'U002', 'Assign', 'Transaction', 'TR785', '[RM QA2] Pickup confirmed for TR785; borrowing started.', '2026-08-13 14:59:28'),
('LOG951', 'U002', 'Update', 'Transaction', 'TR786', '[RM QA2] Overdue return requires Resource Manager attention.', '2026-08-13 22:59:28'),
('LOG952', 'U002', 'Update', 'Borrowing', 'B787', '[RM QA2] Due-today borrowing should remain Borrowed, not Overdue.', '2026-08-14 01:59:28'),
('LOG953', 'U002', 'Approve', 'Transaction', 'TR781', 'Approved borrowing request and reserved RM QA2 Available Wireless Mouse', '2026-08-14 03:01:21'),
('LOG954', 'U002', 'Delete', 'Asset', 'A007', 'Removed asset listing: USB-C Charging Cable', '2026-08-14 10:54:59'),
('LOG955', 'U002', 'Assign', 'Transaction', 'TR784', 'Pickup confirmed; borrowing started for RM QA2 Pickup Reservation', '2026-08-14 13:57:59'),
('LOG956', 'U002', 'Approve', 'Transaction', 'TR782', 'Approved borrowing request and reserved RM QA2 Available Reference Book', '2026-08-14 13:58:08'),
('LOG957', 'U002', 'Update', 'Asset', 'A883', 'Updated asset information: RM TEST Pending Desk Lamp', '2026-08-14 14:17:04'),
('LOG958', 'U002', 'Update', 'Asset', 'A883', 'Updated asset information: RM TEST Pending Desk Lamp', '2026-08-14 14:17:33'),
('LOG959', 'U002', 'Update', 'Asset', 'A883', 'Updated asset information: RM TEST Pending Desk Lamp', '2026-08-14 14:17:38'),
('LOG960', 'U002', 'Update', 'Asset', 'A883', 'Updated asset information: RM TEST Pending Desk Lamp', '2026-08-14 14:19:40'),
('LOG961', 'U002', 'Update', 'Asset', 'A883', 'Updated asset information: RM TEST Pending Desk Lampp', '2026-08-14 14:20:32'),
('LOG962', 'U002', 'Update', 'Asset', 'A883', 'Updated asset information: RM TEST Pending Desk Lamp', '2026-08-14 14:20:44'),
('LOG963', 'U002', 'Submit', 'Asset', 'A781', 'Sent asset listing for verification: RM QA2 Pending Wireless Keyboard', '2026-08-14 14:21:06'),
('LOG964', 'U002', 'Create', 'Category', 'C887', 'Created category: music', '2026-08-14 14:21:20'),
('LOG965', 'U002', 'Assign', 'Transaction', 'TR781', 'Pickup confirmed; borrowing started for RM QA2 Available Wireless Mouse', '2026-08-15 15:39:48'),
('LOG966', 'U002', 'Assign', 'Transaction', 'TR761', 'Pickup confirmed; borrowing started for RM QA2 Reserved Item 1', '2026-08-15 15:39:59'),
('LOG967', 'U002', 'Update', 'Transaction', 'TR919', 'Cancelled reservation before pickup. Reason: no avaialble', '2026-08-15 15:54:16'),
('LOG968', 'U002', 'Assign', 'Transaction', 'TR796', 'Pickup confirmed; borrowing started for RM QA2 Reserved Calculator', '2026-08-15 16:12:56'),
('LOG969', 'U002', 'Update', 'Category', 'C887', 'Deactivated category: music', '2026-08-16 09:43:28'),
('LOG970', 'U002', 'Update', 'Category', 'C887', 'Reactivated category: music', '2026-08-16 09:43:31'),
('LOG971', 'U002', 'Update', 'Category', 'C887', 'Deactivated category: music', '2026-08-16 09:47:13'),
('LOG972', 'U002', 'Update', 'Category', 'C887', 'Reactivated category: music', '2026-08-16 09:47:15'),
('LOG973', 'U002', 'Update', 'Asset', 'A765', 'Updated asset information: RM QA2 Reserved Item 5', '2026-08-17 01:26:28'),
('LOG974', 'U002', 'Submit', 'Asset', 'A782', 'Sent asset listing for verification: RM QA2 Pending Database Textbook', '2026-08-17 01:26:55'),
('LOG975', 'U002', 'Login', 'User', 'U002', 'Logged in to system', '2026-08-17 13:15:36');

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
('TR001', 'A002', 'U004', '2026-07-27 07:01:17', 'Completed', 'Request granted for 14-day study period.', '2026-07-27 07:01:17', 'Hi, I need this textbook for my upcoming exam week.'),
('TR002', 'A001', 'U005', '2026-07-27 07:01:17', 'Rejected', 'dont have pickup date and request date', '2026-08-13 15:12:34', 'Can I borrow this mouse for a few days?'),
('TR761', 'A761', 'U784', '2026-08-04 02:59:28', 'In Progress', 'RM QA2 approved reservation 1.', '2026-08-05 02:59:28', 'Limit test reservation 1'),
('TR762', 'A762', 'U784', '2026-08-05 02:59:28', 'Approved', 'RM QA2 approved reservation 2.', '2026-08-06 02:59:28', 'Limit test reservation 2'),
('TR763', 'A763', 'U784', '2026-08-06 02:59:28', 'Approved', 'RM QA2 approved reservation 3.', '2026-08-07 02:59:28', 'Limit test reservation 3'),
('TR764', 'A764', 'U784', '2026-08-07 02:59:28', 'Approved', 'RM QA2 approved reservation 4.', '2026-08-08 02:59:28', 'Limit test reservation 4'),
('TR765', 'A765', 'U784', '2026-08-08 02:59:28', 'Approved', 'RM QA2 approved reservation 5.', '2026-08-09 02:59:28', 'Limit test reservation 5'),
('TR781', 'A786', 'U781', '2026-08-11 02:59:28', 'In Progress', '', '2026-08-14 03:01:21', 'RM QA2: Please approve this normal borrowing request.'),
('TR782', 'A787', 'U782', '2026-08-12 02:59:28', 'Approved', '', '2026-08-14 13:58:08', 'RM QA2: Use this request to test rejection with a reason.'),
('TR783', 'A788', 'U785', '2026-08-13 02:59:28', 'Pending', NULL, NULL, 'RM QA2: Member has only 5 EcoPoints; approval should still be allowed.'),
('TR784', 'A789', 'U781', '2026-08-10 02:59:28', 'In Progress', 'RM QA2 approved for pickup.', '2026-08-11 02:59:28', 'RM QA2: Approved reservation for Confirm Pickup.'),
('TR785', 'A790', 'U782', '2026-08-08 02:59:28', 'In Progress', 'RM QA2 pickup confirmed.', '2026-08-09 02:59:28', 'RM QA2: Active on-time borrowing.'),
('TR786', 'A791', 'U783', '2026-08-02 02:59:28', 'In Progress', 'RM QA2 pickup confirmed.', '2026-08-03 02:59:28', 'RM QA2: This borrowing is overdue.'),
('TR787', 'A792', 'U781', '2026-08-09 02:59:28', 'In Progress', 'RM QA2 pickup confirmed.', '2026-08-10 02:59:28', 'RM QA2: Due today; must not be overdue.'),
('TR788', 'A793', 'U782', '2026-07-23 02:59:28', 'Completed', 'RM QA2 completed on time.', '2026-07-24 02:59:28', 'RM QA2: Completed on-time history.'),
('TR789', 'A794', 'U781', '2026-07-21 02:59:28', 'Completed', 'RM QA2 completed late with penalty.', '2026-07-22 02:59:28', 'RM QA2: Completed late history.'),
('TR790', 'A775', 'U782', '2026-07-31 02:59:28', 'Rejected', 'RM QA2 rejection reason: item no longer needed.', '2026-08-01 02:59:28', 'RM QA2: Historical rejected request.'),
('TR791', 'A776', 'U781', '2026-08-01 02:59:28', 'Cancelled', 'RM QA2 cancellation reason: pickup cancelled.', '2026-08-02 02:59:28', 'RM QA2: Historical cancelled reservation.'),
('TR792', 'A777', 'U783', '2026-08-13 02:59:28', 'Pending', NULL, NULL, 'RM QA2 BLOCK: requester currently has an overdue unreturned asset.'),
('TR793', 'A778', 'U784', '2026-08-13 02:59:28', 'Pending', NULL, NULL, 'RM QA2 BLOCK: requester already has 5 active Approved borrowings.'),
('TR794', 'A779', 'U781', '2026-08-14 02:59:28', 'Pending', NULL, NULL, 'RM QA2 BLOCK: expected return date is missing.'),
('TR795', 'A780', 'U782', '2026-08-14 02:59:28', 'Pending', NULL, NULL, 'RM QA2 BLOCK: requested pickup date has already passed.'),
('TR796', 'A773', 'U782', '2026-08-09 02:59:28', 'In Progress', 'RM QA2 approved reservation for cancellation.', '2026-08-10 02:59:28', 'RM QA2: Use this one to test Cancel Reservation.'),
('TR797', 'A772', 'U785', '2026-08-14 02:59:28', 'Pending', NULL, NULL, 'RM QA2 BLOCK: return date is the same date as pickup date.'),
('TR798', 'A771', 'U785', '2026-08-14 02:59:28', 'Pending', NULL, NULL, 'RM QA2 BLOCK: asset is Published and Approved but currently Unavailable.'),
('TR851', 'A851', 'U851', '2026-07-30 16:12:30', 'In Progress', 'RETURN TEST pickup confirmed.', '2026-07-31 16:12:30', 'RETURN TEST overdue sample.'),
('TR852', 'A852', 'U852', '2026-07-28 16:12:30', 'Completed', 'RETURN TEST pickup confirmed.', '2026-07-29 16:12:30', 'RETURN TEST overdue sample.'),
('TR853', 'A853', 'U853', '2026-07-26 16:12:30', 'Completed', 'RETURN TEST pickup confirmed.', '2026-07-27 16:12:30', 'RETURN TEST overdue sample.'),
('TR854', 'A854', 'U854', '2026-07-22 16:12:30', 'Completed', 'RETURN TEST pickup confirmed.', '2026-07-23 16:12:30', 'RETURN TEST overdue sample.'),
('TR861', 'A861', 'U884', '2026-08-03 12:35:45', 'In Progress', 'RM TEST approved reservation 1.', '2026-08-04 12:35:45', 'Limit test reservation 1'),
('TR862', 'A862', 'U884', '2026-08-04 12:35:45', 'In Progress', 'RM TEST approved reservation 2.', '2026-08-05 12:35:45', 'Limit test reservation 2'),
('TR863', 'A863', 'U884', '2026-08-05 12:35:45', 'Approved', 'RM TEST approved reservation 3.', '2026-08-06 12:35:45', 'Limit test reservation 3'),
('TR864', 'A864', 'U884', '2026-08-06 12:35:45', 'Approved', 'RM TEST approved reservation 4.', '2026-08-07 12:35:45', 'Limit test reservation 4'),
('TR865', 'A865', 'U884', '2026-08-07 12:35:45', 'Approved', 'RM TEST approved reservation 5.', '2026-08-08 12:35:45', 'Limit test reservation 5'),
('TR881', 'A886', 'U881', '2026-08-10 12:35:45', 'In Progress', '', '2026-08-13 15:15:47', 'RM TEST: Please approve this normal borrowing request.'),
('TR882', 'A887', 'U882', '2026-08-11 12:35:45', 'In Progress', '', '2026-08-13 15:15:59', 'RM TEST: Use this request to test rejection with a reason.'),
('TR883', 'A888', 'U885', '2026-08-12 12:35:45', 'Approved', '', '2026-08-13 15:26:02', 'RM TEST: Member has only 5 EcoPoints; approval should still be allowed.'),
('TR884', 'A889', 'U881', '2026-08-09 12:35:45', 'In Progress', 'RM TEST approved for pickup.', '2026-08-10 12:35:45', 'RM TEST: Approved reservation for Confirm Pickup.'),
('TR885', 'A890', 'U882', '2026-08-07 12:35:45', 'Completed', 'RM TEST pickup confirmed.', '2026-08-08 12:35:45', 'RM TEST: Active on-time borrowing.'),
('TR886', 'A891', 'U883', '2026-08-01 12:35:45', 'Completed', 'RM TEST pickup confirmed.', '2026-08-02 12:35:45', 'RM TEST: This borrowing is overdue.'),
('TR887', 'A892', 'U881', '2026-08-08 12:35:45', 'In Progress', 'RM TEST pickup confirmed.', '2026-08-09 12:35:45', 'RM TEST: Due today; must not be overdue.'),
('TR888', 'A893', 'U882', '2026-07-22 12:35:45', 'Completed', 'RM TEST completed on time.', '2026-07-23 12:35:45', 'RM TEST: Completed on-time history.'),
('TR889', 'A894', 'U881', '2026-07-20 12:35:45', 'Completed', 'RM TEST completed late with penalty.', '2026-07-21 12:35:45', 'RM TEST: Completed late history.'),
('TR890', 'A875', 'U882', '2026-07-30 12:35:45', 'Rejected', 'RM TEST rejection reason: item no longer needed.', '2026-07-31 12:35:45', 'RM TEST: Historical rejected request.'),
('TR891', 'A876', 'U881', '2026-07-31 12:35:45', 'Cancelled', 'RM TEST cancellation reason: pickup cancelled.', '2026-08-01 12:35:45', 'RM TEST: Historical cancelled reservation.'),
('TR892', 'A877', 'U883', '2026-08-12 12:35:45', 'Rejected', 'overdue unreturend asset', '2026-08-13 15:16:26', 'RM TEST BLOCK: requester currently has an overdue unreturned asset.'),
('TR893', 'A878', 'U884', '2026-08-12 12:35:45', 'Rejected', 'you have more than 5', '2026-08-13 15:36:10', 'RM TEST BLOCK: requester already has 5 active Approved borrowings.'),
('TR894', 'A879', 'U881', '2026-08-13 12:35:45', 'Rejected', 'renew the date', '2026-08-13 15:37:25', 'RM TEST BLOCK: expected return date is missing.'),
('TR895', 'A880', 'U882', '2026-08-13 12:35:45', 'Rejected', 'pickupdate laready passed', '2026-08-13 15:27:24', 'RM TEST BLOCK: requested pickup date has already passed.'),
('TR896', 'A873', 'U882', '2026-08-08 12:35:45', 'In Progress', 'RM TEST approved reservation for cancellation.', '2026-08-09 12:35:45', 'RM TEST: Use this one to test Cancel Reservation.'),
('TR897', 'A872', 'U885', '2026-08-13 12:35:45', 'Pending', NULL, NULL, 'RM TEST BLOCK: return date is the same date as pickup date.'),
('TR898', 'A871', 'U885', '2026-08-13 12:35:45', 'Rejected', 'not available', '2026-08-13 15:36:32', 'RM TEST BLOCK: asset is Published and Approved but currently Unavailable.'),
('TR901', 'A901', 'U901', '2026-08-15 15:39:26', 'Pending', NULL, NULL, 'TEST 1: Future booking more than 7 days away.'),
('TR902', 'A902', 'U901', '2026-08-15 15:39:26', 'Pending', NULL, NULL, 'TEST 2: Pickup within seven days.'),
('TR903', 'A903', 'U902', '2026-08-15 15:39:26', 'Approved', NULL, '2026-08-15 15:39:26', 'Existing confirmed booking for conflict test.'),
('TR904', 'A903', 'U901', '2026-08-15 15:39:26', 'Pending', NULL, NULL, 'TEST 3: Should fail the seven-day schedule protection.'),
('TR905', 'A905', 'U902', '2026-08-15 15:39:26', 'In Progress', NULL, '2026-08-05 15:39:26', 'Existing overdue borrowing.'),
('TR906', 'A906', 'U902', '2026-08-15 15:39:26', 'Pending', NULL, NULL, 'TEST 4: Requester currently has overdue borrowing.'),
('TR910', 'A910', 'U903', '2026-08-15 15:39:26', 'Approved', NULL, '2026-08-15 15:39:26', 'Active reservation 1.'),
('TR911', 'A911', 'U903', '2026-08-15 15:39:26', 'Approved', NULL, '2026-08-15 15:39:26', 'Active reservation 2.'),
('TR912', 'A912', 'U903', '2026-08-15 15:39:26', 'Approved', NULL, '2026-08-15 15:39:26', 'Active reservation 3.'),
('TR913', 'A913', 'U903', '2026-08-15 15:39:26', 'Approved', NULL, '2026-08-15 15:39:26', 'Active reservation 4.'),
('TR914', 'A914', 'U903', '2026-08-15 15:39:26', 'Approved', NULL, '2026-08-15 15:39:26', 'Active reservation 5.'),
('TR915', 'A915', 'U903', '2026-08-15 15:39:26', 'Pending', NULL, NULL, 'TEST 5: Sixth active request should be blocked.'),
('TR916', 'A916', 'U901', '2026-08-15 15:39:26', 'Pending', NULL, NULL, 'TEST 6: Operationally unavailable asset.'),
('TR917', 'A917', 'U901', '2026-08-15 15:39:26', 'Pending', NULL, NULL, 'TEST 7: Return date is before pickup date.'),
('TR918', 'A918', 'U902', '2026-08-15 15:39:26', 'In Progress', NULL, '2026-08-10 15:39:26', 'Previous borrower currently has the asset.'),
('TR919', 'A918', 'U901', '2026-08-15 15:39:26', 'Cancelled', 'no avaialble', '2026-08-15 15:39:26', 'TEST 8: Future reservation waiting for previous return.'),
('TR920', 'A920', 'U902', '2026-08-15 15:39:26', 'In Progress', NULL, '2026-08-05 15:39:26', 'Current borrower to be returned through inspection.'),
('TR921', 'A920', 'U901', '2026-08-15 15:39:26', 'Approved', NULL, '2026-08-15 15:39:26', 'Upcoming protected reservation after overdue return.');

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
('U004', 'Tan Miao Miao', 'tan@mail.apu.edu.my', 'password123', 'Member', 'Active', 295, '2026-07-27 07:01:16'),
('U005', 'Teo Miao Miao', 'teo@mail.apu.edu.my', 'password123', 'Member', 'Active', 85, '2026-07-27 07:01:16'),
('U006', 'Jia Qi Lim', 'limjiaqi735@gmail.com', '$2y$10$PQIKXR2iwXQnxpG0wMksXuX6Jr1FfQmWQfP7dBfBcsUQWuuPiNzFK', 'Member', 'Active', 0, '2026-07-28 05:57:29'),
('U781', 'RM QA2 Alice', 'rmqa2.alice@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 285, '2026-04-16 02:59:28'),
('U782', 'RM QA2 Brandon', 'rmqa2.brandon@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 180, '2026-05-06 02:59:28'),
('U783', 'RM QA2 Chloe Overdue', 'rmqa2.chloe@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 95, '2026-05-16 02:59:28'),
('U784', 'RM QA2 Daniel Limit', 'rmqa2.daniel@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 420, '2026-05-26 02:59:28'),
('U785', 'RM QA2 Emma LowPoints', 'rmqa2.emma@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 5, '2026-06-15 02:59:28'),
('U786', 'RM QA2 Asset Owner', 'rmqa2.owner@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 250, '2026-03-17 02:59:28'),
('U850', 'Return Test Asset Owner', 'return.owner@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 300, '2026-03-16 16:12:30'),
('U851', 'Return Test Amelia', 'return.amelia@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 220, '2026-05-05 16:12:30'),
('U852', 'Return Test Bryan', 'return.bryan@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 160, '2026-05-15 16:12:30'),
('U853', 'Return Test Carmen', 'return.carmen@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 105, '2026-05-25 16:12:30'),
('U854', 'Return Test Dylan', 'return.dylan@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 75, '2026-06-04 16:12:30'),
('U881', 'RM Test Alice', 'rmtest.alice@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 185, '2026-04-15 12:35:45'),
('U882', 'RM Test Brandon', 'rmtest.brandon@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 175, '2026-05-05 12:35:45'),
('U883', 'RM Test Chloe Overdue', 'rmtest.chloe@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', -5, '2026-05-15 12:35:45'),
('U884', 'RM Test Daniel Limit', 'rmtest.daniel@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 420, '2026-05-25 12:35:45'),
('U885', 'RM Test Emma LowPoints', 'rmtest.emma@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 5, '2026-06-14 12:35:45'),
('U886', 'RM Test Asset Owner', 'rmtest.owner@apu.edu.my', '$2y$12$uMsIP9exLFL2MLhBoxkGDOBqOyU8N3RioOrNIeBHrrK3YIFiodt96', 'Member', 'Active', 250, '2026-03-16 12:35:45'),
('U901', 'Normal Test Member', 'normal.test@mail.apu.edu.my', 'password123', 'Member', 'Active', 300, '2026-08-15 15:39:26'),
('U902', 'Overdue Test Member', 'overdue.test@mail.apu.edu.my', 'password123', 'Member', 'Active', 220, '2026-08-15 15:39:26'),
('U903', 'Limit Test Member', 'limit.test@mail.apu.edu.my', 'password123', 'Member', 'Active', 180, '2026-08-15 15:39:26');

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
('V004', 'U005', 'U003', 'A002', 'Asset', 'Approved', 'In a very good conditions.', '2026-08-11 17:19:49'),
('V005', 'U006', NULL, 'A003', 'Asset', 'Pending', NULL, NULL),
('V006', 'U005', 'U003', 'A004', 'Asset', 'Rejected', 'Execllent', '2026-08-12 03:28:48');

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
