
#
# Table structure for table 'sys_file_metadata'
#
CREATE TABLE sys_file_metadata (
    contributor  varchar(255) DEFAULT '',
    created int(11) DEFAULT '0' NOT NULL,
    changed int(11) DEFAULT '0' NOT NULL,
    subject  varchar(255) DEFAULT '',
    coverage text,
    is_manja smallint(5) unsigned DEFAULT '0' NOT NULL,
);


