SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `dbconcctrl`
--
CREATE DATABASE IF NOT EXISTS `dbconcctrl` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `dbconcctrl`;

-- --------------------------------------------------------

--
-- Table structure for table `lock_table`
--

CREATE TABLE `lock_table` (
  `lock_item` varchar(10) NOT NULL COMMENT 'The item name in the lock',
  `lock_state` tinyint(4) NOT NULL COMMENT '1 for Read/Shared Lock, 2 for Write/Exclusive Lock',
  `tids` varchar(40) NOT NULL COMMENT 'The TID(s) with lock',
  `w_tids_r` varchar(40) DEFAULT NULL COMMENT 'Waiting TIDs for Read Lock',
  `w_tid_w` varchar(40) DEFAULT NULL COMMENT 'Waiting TID for Write Lock'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `trans_table`
--

CREATE TABLE `trans_table` (
  `tid` tinyint(4) NOT NULL COMMENT 'Transaction ID',
  `trans_state` tinyint(4) NOT NULL COMMENT '1 is active, 2 is blocked/waiting, 3 is committed, -1 aborted/cancelled',
  `t_timestamp` tinyint(4) NOT NULL COMMENT 'Timestamp of the transaction'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;