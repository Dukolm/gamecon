#!/usr/bin/env bash
set -x
sudo -u www-data composer --working-dir=/var/www/html/gamecon install && \
sudo -u www-data composer --working-dir=/var/www/html/gamecon/adminer install && \
chgrp -R www-data /var/www/html/gamecon/cache && \
chmod -R g+rw /var/www/html/gamecon/cache && \
chgrp -R www-data /var/www/html/gamecon/web/soubory/systemove/* && \
chmod -R g+rw /var/www/html/gamecon/web/soubory/systemove/* && \
chgrp -R www-data /var/www/html/gamecon/adminer && \
chmod -R g+rw /var/www/html/gamecon/adminer && \
apache2-foreground