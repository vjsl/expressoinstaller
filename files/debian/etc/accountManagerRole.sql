-- rodar com o comando:
-- psql -U postgres -d db_<ORGANIZACAO> -a -f <PATH>/files/debian/etc/accountManagerRole.sql

-- Cria a role para gerente
INSERT INTO tine20_roles (id, name, description, created_by, creation_time, last_modified_by, last_modified_time) VALUES (3, 'manager role', 'manager role for tine. this role has rights to manage accounts.', NULL , NULL , NULL, NULL);

-- Cria a permissao para o grupo
INSERT INTO tine20_role_accounts (id, role_id, account_type, account_id) VALUES (3, 3, 'group', '902');

-- Cria as ACLs para a role de gerente
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES (3, (select id from tine20_applications where name = 'Admin'), 'run');
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES ( 3, (select id from tine20_applications where name = 'Admin'), 'manage_accounts');
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES ( 3, (select id from tine20_applications where name = 'Admin'), 'manage_ldap_maillists');
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES ( 3, (select id from tine20_applications where name = 'Tasks'), 'run');
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES ( 3, (select id from tine20_applications where name = 'Addressbook'), 'run');
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES ( 3, (select id from tine20_applications where name = 'Expressomail'), 'run');
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES ( 3, (select id from tine20_applications where name = 'Expressomail'), 'manage_accounts');
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES ( 3, (select id from tine20_applications where name = 'ActiveSync'), 'run');
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES ( 3, (select id from tine20_applications where name = 'Calendar'), 'run');
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES ( 3, (select id from tine20_applications where name = 'Tinebase'), 'run');
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES ( 3, (select id from tine20_applications where name = 'Tinebase'), 'manage_own_state');
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES ( 3, (select id from tine20_applications where name = 'Tinebase'), 'report_bugs');
INSERT INTO tine20_role_rights ( role_id, application_id, "right") VALUES ( 3, (select id from tine20_applications where name = 'Tinebase'), 'check_version');
