
apt update && apt install -y imagemagick 
sudo apt install -y postgresql postgresql-contrib php-xml
sudo apt-get install -y jq moreutils
pg_ctlcluster 13 main start
apt install -y php-pgsql
sudo -u postgres psql -c "CREATE ROLE typo3 WITH LOGIN INHERIT CONNECTION LIMIT -1 PASSWORD 'typo3';" 
sudo -u postgres psql -c "CREATE DATABASE typo3 WITH OWNER = typo3 ENCODING = 'UTF8' CONNECTION LIMIT = -1;"
cd /opt
composer create-project typo3/cms-base-distribution:^11 typo3
cd typo3
composer exec typo3cms install:setup -- \
    --no-interaction \
    --database-driver=pdo_pgsql \
    --database-user-name=typo3 \
    --database-user-password=typo3 \
    --database-host-name=127.0.0.1 \
    --database-port=5432 \
    --database-name=typo3 \
    --use-existing-database \
    --admin-user-name=admin \
    --admin-password=password \
    --site-setup-type=site

sed -i  "/'SYS'/a\'trustedHostsPattern\'\ \=\>\ '.*'," public/typo3conf/LocalConfiguration.php
composer config repositories.repo-name vcs https://github.com/manjadigital/Add-On_Typo3
composer require manja/typo3-storage-connector:dev-main
composer exec typo3cms database:update
