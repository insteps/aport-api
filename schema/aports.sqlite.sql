CREATE TABLE 'packages' (
        'id' INTEGER primary key,
        'name' TEXT,
        'version' TEXT,
        'description' TEXT,
        'url' TEXT,
        'license' TEXT,
        'arch' TEXT,
        'branch' TEXT,
        'repo' TEXT,
        'checksum' TEXT,
        'size' INTEGER,
        'installed_size' INTEGER,
        'origin' TEXT,
        'maintainer' INTEGER,
        'build_time' INTEGER,
        'commit' TEXT
    , fid INTEGER);
CREATE TABLE 'files' (
        'id' INTEGER primary key,
        'file' TEXT,
        'path' TEXT,
        'pkgname' TEXT,
        'pid' INTEGER REFERENCES packages(id) ON DELETE CASCADE
    );
CREATE TABLE 'provides' (
        'name' TEXT,
        'version' TEXT,
        'operator' TEXT,
        'pid' INTEGER REFERENCES packages(id) ON DELETE CASCADE
    );
CREATE TABLE 'depends' (
        'name' TEXT,
        'version' TEXT,
        'operator' TEXT,
        'pid' INTEGER REFERENCES packages(id) ON DELETE CASCADE
    );
CREATE TABLE 'install_if' (
        'name' TEXT,
        'version' TEXT,
        'operator' TEXT,
        'pid' INTEGER REFERENCES packages(id) ON DELETE CASCADE
    );
CREATE TABLE maintainer (
        'id' INTEGER primary key,
        'name' TEXT,
        'email' TEXT
    );
CREATE TABLE 'repoversion' (
        'branch' TEXT,
        'repo' TEXT,
        'arch' TEXT,
        'version' TEXT
    );
CREATE TABLE 'flagged' ('fid' INTEGER primary key,'created' INTEGER,'reporter' TEXT,'new_version' TEXT,'message' TEXT);
CREATE INDEX 'packages_name' on 'packages' (name) ;
CREATE INDEX 'packages_maintainer' on 'packages' (maintainer) ;
CREATE INDEX 'packages_build_time' on 'packages' (build_time) ;
CREATE INDEX 'files_file' on 'files' (file) ;
CREATE INDEX 'files_path' on 'files' (path) ;
CREATE INDEX 'files_pkgname' on 'files' (pkgname) ;
CREATE INDEX 'files_pid' on 'files' (pid) ;
CREATE INDEX 'provides_name' on 'provides' (name);
CREATE INDEX 'provides_pid' on 'provides' (pid);
CREATE INDEX 'depends_name' on 'depends' (name);
CREATE INDEX 'depends_pid' on 'depends' (pid);
CREATE INDEX 'install_if_name' on 'install_if' (name);
CREATE INDEX 'install_if_pid' on 'install_if' (pid);
CREATE INDEX 'maintainer_name' 
        on maintainer (name);
CREATE UNIQUE INDEX 'repoversion_version' 
        on repoversion (branch, repo, arch);
