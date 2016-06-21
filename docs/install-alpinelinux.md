
## Requirements:
* php-apache2
* php-pdo_sqlite
* php-phalcon
* php-xml


1. download and install aport-api
2. edit index.php to change variables
3. `apk add php-apache2 php-pdo_sqlite php-phalcon php-xml`
4. edit httpd.conf to enable rewrite rules
5. start apache2

__Note__: php pkgs available could have prefixes of format
php5-<module> or php7-<module>. 
Do check branch/repo/arch for prefixes or do `apk search php`

