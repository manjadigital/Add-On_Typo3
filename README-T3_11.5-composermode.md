# variante für version 11.5 im composermode
```sh
FAL_MANJA=$PWD
docker kill fal_manja_c
docker run --rm -it  -d -p 60002:8000 -v $FAL_MANJA:/opt/typo3_storage_connector --name fal_manja_c git.manjadigital.de:4567/manja/manja_container/base-webrtenv:debian-11
docker exec -it fal_manja_c bash
```

# in container:
```sh
apt update && apt install imagemagick 

sudo apt install postgresql postgresql-contrib php-xml
sudo apt-get install jq moreutils
pg_ctlcluster 13 main start
apt install php-pgsql

sudo -u postgres psql -c "CREATE ROLE typo3 WITH LOGIN INHERIT CONNECTION LIMIT -1 PASSWORD 'typo3';" 
sudo -u postgres psql -c "CREATE DATABASE typo3 WITH OWNER = typo3 ENCODING = 'UTF8' CONNECTION LIMIT = -1;"

cd /opt
composer create-project typo3/cms-base-distribution:^11 typo3
cd typo3

jq '.repositories[0] = {"type": "path", "url": "./packages/*/"}' composer.json | sponge composer.json

mkdir packages
#ln -s /opt/fal_manja packages/fal_manja
ln -s /opt/typo3_storage_connector packages/typo3_storage_connector

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


#composer require jokumer/fal-manja:@dev
composer require manja/typo3-storage-connector


TYPO3_CONTEXT=Development php -S 0.0.0.0:8000 -t public
```

After Login: Maintenance -> Analyze Database -> Apply Changes

To avoid Filelist errors: dont fill processed path