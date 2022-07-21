SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS = @@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION = @@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins`
(
    `id`                    int(11)      NOT NULL AUTO_INCREMENT,
    `login`                 varchar(100) NOT NULL,
    `password`              varchar(100) NOT NULL,
    `parent_id`             int(11)               DEFAULT NULL,
    `created_at`            timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `remember_token`        varchar(100),
    `is_superadmin`         tinyint(1)   NOT NULL DEFAULT '0',
    `language`              char(2)               DEFAULT 'en',
    `ip`                    varchar(15)           DEFAULT '192.168.1.1',
    `role`                  varchar(100) NOT NULL DEFAULT '',
    `is_active`             tinyint(1)   NOT NULL DEFAULT '1',
    `name`                  varchar(200) NOT NULL DEFAULT '',
    `email`                 varchar(100)          DEFAULT NULL,
    `timezone`              varchar(50)  NOT NULL DEFAULT 'UTC',
    `not_changeable_column` varchar(50)  NOT NULL DEFAULT 'not changable',
    `big_data`              text         NOT NULL DEFAULT 'biiiiiiig data',
    PRIMARY KEY (`id`),
    UNIQUE KEY `login` (`login`),
    UNIQUE KEY `email` (`email`),
    KEY `parent_id` (`parent_id`),
    KEY `remember_token` (`remember_token`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  AUTO_INCREMENT = 1;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `info_pages`;
CREATE TABLE `info_pages`
(
    `id`           int(11)      NOT NULL AUTO_INCREMENT,
    `code`         varchar(255) NOT NULL,
    `lang`         char(2)      NOT NULL DEFAULT 'en',
    `title`        varchar(500) NOT NULL,
    `link_title`   varchar(255) NOT NULL DEFAULT '',
    `content`      text,
    `is_important` tinyint(1)   NOT NULL DEFAULT '0',
    `created_at`   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `code` (`code`),
    KEY `lang` (`lang`),
    KEY `title` (`title`(255))
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  AUTO_INCREMENT = 1;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings`
(
    `id`    int(11)      NOT NULL AUTO_INCREMENT,
    `key`   varchar(100) NOT NULL,
    `value` text         NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `key` (`key`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  AUTO_INCREMENT = 1;

-- --------------------------------------------------------

ALTER TABLE `admins`
    ADD CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT = @OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS = @OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION = @OLD_COLLATION_CONNECTION */;
