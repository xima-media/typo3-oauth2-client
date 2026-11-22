INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `sys_language_uid`, `l10n_parent`, `l10n_source`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`, `doktype`, `is_siteroot`)
VALUES (1, 0, 'Main', '/', 0, 0, 0, 1, 1, 31, 31, 1, 1, 1);

INSERT INTO `be_groups` (`uid`, `pid`, `title`, `groupMods`)
VALUES (1, 0, 'Editors', 'user_setup');

-- "typo3 setup" creates _cli_ and admin with low uids; OAuth personas carry no usable password
INSERT INTO `be_users` (`uid`, `pid`, `username`, `password`, `admin`, `usergroup`, `email`, `realName`, `disable`, `tx_oauth2_client_configs`)
VALUES
	(10, 0, 'be-linked', '', 0, '1', 'be-linked@example.org', 'BE linked', 0, 2),
	(11, 0, 'editor', '', 0, '1', 'editor@example.org', 'Editor', 0, 0),
	(12, 0, 'be-twice-a', '', 0, '1', 'be-twice-a@example.org', 'BE twice A', 0, 1),
	(13, 0, 'be-twice-b', '', 0, '1', 'be-twice-b@example.org', 'BE twice B', 0, 1),
	(14, 0, 'be-disabled', '', 0, '1', 'be-disabled@example.org', 'BE disabled', 1, 1),
	(15, 0, 'be-linker', '', 0, '1', 'be-linker@example.org', 'BE linker', 0, 1);

INSERT INTO `tx_oauth2_beuser_provider_configuration` (`uid`, `pid`, `parentid`, `provider`, `identifier`)
VALUES
	(1, 0, 10, 'mock', 'be-linked'),
	(2, 0, 12, 'mock', 'be-twice'),
	(3, 0, 13, 'mock', 'be-twice'),
	(4, 0, 14, 'mock', 'be-disabled'),
	(5, 0, 10, 'mock-fe', 'be-linked'),
	(6, 0, 15, 'mock', 'be-linker');
