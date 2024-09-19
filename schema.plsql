
CREATE TABLE stat_db
(
  id          SERIAL    NOT NULL,
  dbhost      TEXT      NOT NULL,
  dbport      INTEGER   NOT NULL DEFAULT 5432,
  dbname      TEXT      NOT NULL,
  dbuser      TEXT      NOT NULL,
  dbpass      TEXT               DEFAULT NULL,
  vhost       TEXT               DEFAULT NULL,
  ignore      BOOLEAN   NOT NULL DEFAULT false,
  oldstyle    BOOLEAN   NOT NULL DEFAULT false,
  startdate   DATE      NOT NULL DEFAULT now(),
  lastupdate  DATE               DEFAULT NULL,
  visiblefrom DATE               DEFAULT NULL
);

CREATE UNIQUE INDEX idx_stat_db_id ON stat_db(id);
CREATE UNIQUE INDEX idx_stat_db_dbname_dbhost ON stat_db(lower(dbname), lower(dbhost));
CREATE UNIQUE INDEX idx_stat_db_vhost_ignore ON stat_db(lower(vhost), ignore);
ALTER TABLE ONLY stat_db ADD CONSTRAINT stat_faktura_db_chk CHECK (dbport >= 1 AND dbport <= 65534 AND dbhost <> '' AND dbname <> '' AND dbuser <> '');

CREATE TABLE stat_type
(
  id       SERIAL  NOT NULL,
  typename TEXT    NOT NULL,
  issum    BOOLEAN NOT NULL
);

CREATE UNIQUE INDEX idx_stat_type_id ON stat_type(id);
CREATE UNIQUE INDEX idx_stat_type_typename ON stat_type(lower(typename));

INSERT INTO stat_type (typename, issum) VALUES ('faktura', false);
INSERT INTO stat_type (typename, issum) VALUES ('efaktura', false);
INSERT INTO stat_type (typename, issum) VALUES ('bilag', false);
INSERT INTO stat_type (typename, issum) VALUES ('usersactive', true);
INSERT INTO stat_type (typename, issum) VALUES ('usersdisabled', true);


CREATE TABLE stat_faktura
(
  id       SERIAL  NOT NULL,
  dbref    INTEGER NOT NULL,
  stamp    DATE    NOT NULL,
  org      TEXT    NOT NULL,
  stattype INTEGER NOT NULL,
  cnt      INTEGER NOT NULL
);

CREATE UNIQUE INDEX idx_stat_faktura_id ON stat_faktura(id);
CREATE UNIQUE INDEX idx_stat_faktura_unique_entry ON stat_faktura(dbref, stamp, org, stattype);
CREATE INDEX idx_stat_faktura_stamt_stattype ON stat_faktura(stamp, stattype);
ALTER TABLE ONLY stat_faktura ADD CONSTRAINT stat_faktura_dbref_fkey FOREIGN KEY (dbref) REFERENCES stat_db(id);
ALTER TABLE ONLY stat_faktura ADD CONSTRAINT stat_faktura_stattype_fkey FOREIGN KEY (stattype) REFERENCES stat_type(id);

-- Test-data
INSERT INTO stat_db (dbhost, dbname, dbuser, startdate) VALUES ('localhost', 'jjensen',    'postgres', '2010-10-06');
INSERT INTO stat_db (dbhost, dbname, dbuser, startdate) VALUES ('localhost', 'regnskoven', 'postgres', '2010-11-29');
INSERT INTO stat_db (dbhost, dbname, dbuser, startdate) VALUES ('localhost', 'skanlog',    'postgres', '2010-11-30');
INSERT INTO stat_db (dbhost, dbname, dbuser, startdate) VALUES ('localhost', 'fipogroup',  'postgres', '2010-04-28');
