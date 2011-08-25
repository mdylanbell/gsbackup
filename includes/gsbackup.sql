DROP TABLE IF EXISTS backups, configurations;

CREATE TABLE backups (
`id` INT NOT NULL AUTO_INCREMENT,
`configuration` INT,
`filename` VARCHAR(128) NOT NULL,
`filesize` VARCHAR(32) NOT NULL,
`date_created` VARCHAR(32) NOT NULL,
`uploaded_to_s3` BOOLEAN NOT NULL,
`s3_remote_path` VARCHAR(128),
`successful` BOOLEAN NOT NULL,
PRIMARY KEY(id),
FOREIGN KEY(configuration) REFERENCES configurations(id)
);

CREATE TABLE configurations (
`id` INT NOT NULL AUTO_INCREMENT,
`name` VARCHAR(64) NOT NULL,
`num_backups` INT NOT NULL,
`databases` VARCHAR(1028),
`one_database_file` BOOLEAN NOT NULL,
`domains` VARCHAR(1028),
`custom_s3_config` BOOLEAN NOT NULL,
`s3_enabled` BOOLEAN,
`s3_remote_path` VARCHAR(128),
`last_attempted_backup` DATETIME,
PRIMARY KEY(id)
);
