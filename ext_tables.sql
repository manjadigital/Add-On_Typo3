
#
# Table structure for table 'sys_file_metadata'
#
CREATE TABLE sys_file_metadata (
    is_manja smallint(5) unsigned DEFAULT '0' NOT NULL,
    subject  text DEFAULT '',
    coverage text,
    keywords  text,
    contributor  text DEFAULT '',
    publisher  text DEFAULT '',
    copyright  text DEFAULT '',
    creator  text DEFAULT '',
    created int(11) DEFAULT '0' NOT NULL,
    changed int(11) DEFAULT '0' NOT NULL
);


