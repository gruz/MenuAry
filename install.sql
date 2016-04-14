CREATE TABLE IF NOT EXISTS `#__menuary` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`context` varchar(255) NOT NULL,
	`itemid` int(11) NOT NULL COMMENT 'Itemid in the #__menu',
	`content_id` int(11) NOT NULL COMMENT 'ID of the category in #__categories, or of an article in #__content',
	`ruleUniqID` varchar(32) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `context` (`context`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
DELETE FROM `#__menu` WHERE `menutype` LIKE 'menuary%';
