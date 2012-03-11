CREATE DATABASE capacity;

GRANT ALL ON capacity.* TO 'capacity'@'%' IDENTIFIED BY 'planning';

USE capacity;

CREATE TABLE benchmarks (
  cluster_name varchar(30) NOT NULL default '',
  max_rps float(10,1) default NULL,
  last_updated datetime NOT NULL,
  ip_addr int(10) unsigned NOT NULL,
  PRIMARY KEY  (cluster_name)
);

CREATE TABLE clusters (
  cluster_name varchar(30) NOT NULL default '',
  description varchar(100) NOT NULL default '',
  sort_order int(10) unsigned default NULL,
  last_updated datetime NOT NULL,
  PRIMARY KEY  (cluster_name),
  KEY sort_order (sort_order)
);

CREATE TABLE exceptions (
  cluster_name varchar(30) NOT NULL default '',
  rps float(10,1) default NULL,
  memory float(5,1) default NULL,
  disk float(5,1) default NULL,
  last_updated datetime NOT NULL,
  ip_addr int(10) unsigned NOT NULL,
  PRIMARY KEY  (cluster_name)
);

CREATE TABLE history (
  post_date date NOT NULL,
  cluster_name varchar(30) NOT NULL default '',
  hosts int(10) unsigned default NULL,
  rps float(10,1) default NULL,
  cpu float(5,1) default NULL,
  memory float(5,1) default NULL,
  disk float(5,1) default NULL,
  PRIMARY KEY  (post_date,cluster_name)
);

