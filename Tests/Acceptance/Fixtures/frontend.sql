INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `sys_language_uid`, `l10n_parent`, `l10n_source`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`, `doktype`, `is_siteroot`, `module`)
VALUES
	(2, 1, 'Frontend users', '/frontend-users', 0, 0, 0, 1, 1, 31, 31, 1, 254, 0, 'fe_users'),
	(3, 1, 'Other frontend users', '/other-frontend-users', 0, 0, 0, 1, 1, 31, 31, 1, 254, 0, 'fe_users'),
	(4, 1, 'Login', '/login', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, ''),
	(5, 1, 'Account', '/account', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, ''),
	(6, 0, 'Main (de)', '/', 1, 1, 1, 1, 1, 31, 31, 1, 1, 1, ''),
	(7, 1, 'Login (de)', '/login', 1, 4, 4, 1, 1, 31, 31, 1, 1, 0, ''),
	(20, 0, 'Second', '/', 0, 0, 0, 1, 1, 31, 31, 1, 1, 1, ''),
	(21, 20, 'Login', '/login', 0, 0, 0, 1, 1, 31, 31, 1, 1, 0, '');

INSERT INTO `tt_content` (`uid`, `pid`, `CType`, `header`, `header_layout`, `colPos`, `sys_language_uid`, `l18n_parent`, `l10n_source`)
VALUES
	(1, 4, 'felogin_login', 'Login', '100', 0, 0, 0, 0),
	(2, 4, 'felogin_login', 'Anmelden', '100', 0, 1, 1, 1),
	(3, 5, 'oauth2client_manageproviders', 'Manage providers', '100', 0, 0, 0, 0),
	(4, 21, 'felogin_login', 'Login', '100', 0, 0, 0, 0);

INSERT INTO `fe_groups` (`uid`, `pid`, `title`)
VALUES
	(1, 2, 'Members'),
	(2, 3, 'Other members');

-- OAuth personas carry no usable password
INSERT INTO `fe_users` (`uid`, `pid`, `username`, `password`, `usergroup`, `email`, `name`, `disable`, `tx_oauth2_client_configs`)
VALUES
	(1, 2, 'fe-linked', '', '1', 'fe-linked@example.org', 'FE linked', 0, 1),
	(2, 3, 'fe-otherpid', '', '2', 'fe-otherpid@example.org', 'FE other pid', 0, 1);

INSERT INTO `tx_oauth2_feuser_provider_configuration` (`uid`, `pid`, `parentid`, `provider`, `identifier`)
VALUES
	(1, 0, 1, 'mock', 'fe-linked'),
	(2, 0, 2, 'mock', 'fe-otherpid');
