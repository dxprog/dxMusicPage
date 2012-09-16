-- phpMyAdmin SQL Dump
-- version 3.3.8
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Sep 16, 2012 at 05:50 PM
-- Server version: 5.0.51
-- PHP Version: 5.4.6-1~dotdeb.0

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `dxmp`
--

-- --------------------------------------------------------

--
-- Table structure for table `content`
--

CREATE TABLE IF NOT EXISTS `content` (
  `content_id` bigint(20) NOT NULL auto_increment,
  `content_title` varchar(255) collate utf8_unicode_ci NOT NULL,
  `content_perma` varchar(255) character set latin1 NOT NULL,
  `content_body` text collate utf8_unicode_ci NOT NULL,
  `content_date` int(10) NOT NULL,
  `content_type` varchar(10) character set latin1 NOT NULL,
  `content_parent` bigint(20) NOT NULL,
  `content_meta` text collate utf8_unicode_ci NOT NULL,
  PRIMARY KEY  (`content_id`),
  KEY `content_parent` (`content_parent`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=5934 ;

-- --------------------------------------------------------

--
-- Table structure for table `hits`
--

CREATE TABLE IF NOT EXISTS `hits` (
  `content_id` bigint(20) NOT NULL,
  `hit_ip` varchar(15) character set latin1 NOT NULL,
  `hit_date` int(10) NOT NULL,
  `hit_user` varchar(10) collate utf8_unicode_ci default NULL,
  `hit_type` smallint(6) NOT NULL,
  KEY `content_id` (`content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE IF NOT EXISTS `tags` (
  `content_id` bigint(20) NOT NULL,
  `tag_name` varchar(25) NOT NULL,
  KEY `tag_content` (`content_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `hits`
--
ALTER TABLE `hits`
  ADD CONSTRAINT `fk_content_id` FOREIGN KEY (`content_id`) REFERENCES `content` (`content_id`) ON DELETE CASCADE ON UPDATE CASCADE;
