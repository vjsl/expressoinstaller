service slapd stop
service postgresql stop
service cyrus-imapd stop 
service apache2 stop
rm /tmp/expressov3/*.log
rm -rf /tmp/expressov3
service postfix stop
rm -rf /var/www/html/backup*
