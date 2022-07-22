# variante für version 11.5 im non-composermode
```sh
FAL_MANJA=$PWD
docker kill fal_manja
docker run --rm -it  -d -p 60001:8000 -v $FAL_MANJA:/opt/typo3_storage_connector --name fal_manja git.manjadigital.de:4567/manja/manja_container/base-webrtenv:debian-11
docker exec -it fal_manja bash
```

# in container:
```sh
apt update && apt install imagemagick 

sudo apt install postgresql postgresql-contrib php-xml
pg_ctlcluster 13 main start
apt install php-pgsql

sudo -u postgres psql -c "CREATE ROLE typo3 WITH LOGIN INHERIT CONNECTION LIMIT -1 PASSWORD 'typo3';" 
sudo -u postgres psql -c "CREATE DATABASE typo3 WITH OWNER = typo3 ENCODING = 'UTF8' CONNECTION LIMIT = -1;"

cd opt
curl -L -o typo3_src.tgz https://get.typo3.org/11.5.12
gunzip typo3_src.tgz
tar -xf typo3_src.tar
cd typo3_src-11.5.12
touch FIRST_INSTALL

TYPO3_CONTEXT=Development php -S 0.0.0.0:8000 -t .

ln -s /opt/typo3_storage_connector typo3conf/ext/typo3_storage_connector

TYPO3_CONTEXT=Development php -S 0.0.0.0:8000 -t .

```
To avoid Filelist errors: dont fill processed path