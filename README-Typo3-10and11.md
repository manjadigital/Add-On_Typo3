



### Build for development & testing

```sh
composer install

# or

docker run --rm -it \
  -u $(id -u):$(id -g) \
  --mount type=bind,source=$PWD,target=/opt/source \
  git.manjadigital.de:4567/manja/manja_container/base-buildenv:debian-10 \
  bash -c 'cd /opt/source; composer install --no-interaction --no-progress --ignore-platform-reqs --optimize-autoloader --classmap-authoritative'
```



### Build for release

```sh
composer dump-autoload --no-interaction --ignore-platform-reqs --classmap-authoritative

# or

docker run --rm -it \
  -u $(id -u):$(id -g) \
  --mount type=bind,source=$PWD,target=/opt/source \
  git.manjadigital.de:4567/manja/manja_container/base-buildenv:debian-10 \
  bash -c 'cd /opt/source; composer dump-autoload --no-interaction --ignore-platform-reqs --classmap-authoritative'
```



TODO: steps to build package ..




## Quick TYPO3 Setup for Development

### Typo3 10

```sh

FAL_MANJA=$PWD
#FAL_MANJA=$PWD/public/typo3conf/ext/fal_manja

docker run --rm -it  -d -p 8000:8000 -v $FAL_MANJA:/opt/fal_manja --name fal_manja git.manjadigital.de:4567/manja/manja_container/base-webrtenv:debian-11
docker exec -it fal_manja bash


# in container:
apt update && apt install php-mysqli default-mysql-server imagemagick


cd /opt
composer create-project typo3/cms-base-distribution typo3 ^10
cd typo3

mysqld_safe &

echo "CREATE USER 'typo3'@'localhost' IDENTIFIED BY 'typo3';" | mysql -uroot -hlocalhost
echo "CREATE DATABASE typo3;" | mysql -uroot -hlocalhost
echo "GRANT ALL PRIVILEGES ON typo3.* TO 'typo3'@'localhost';" | mysql -uroot -hlocalhost

composer exec typo3cms install:setup -- \
    --no-interaction \
    --database-user-name=typo3 \
    --database-user-password=typo3 \
    --database-host-name=127.0.0.1 \
    --database-port=3306 \
    --database-name=typo3 \
    --use-existing-database \
    --admin-user-name=admin \
    --admin-password=password \
    --site-setup-type=site


ln -s /opt/fal_manja public/typo3conf/ext/fal_manja

TYPO3_CONTEXT=Development php -S 0.0.0.0:8000 -t public

```


### Typo3 11

```sh

FAL_MANJA=$PWD
#FAL_MANJA=$PWD/public/typo3conf/ext/fal_manja

docker run --rm -it  -d -p 8000:8000 -v $FAL_MANJA:/opt/fal_manja --name fal_manja11 git.manjadigital.de:4567/manja/manja_container/base-webrtenv:debian-11
docker exec -it fal_manja11 bash


# in container:
apt update && apt install php-mysqli default-mysql-server imagemagick


cd /opt
composer create-project typo3/cms-base-distribution typo3 ^11
cd typo3

mysqld_safe &

echo "CREATE USER 'typo3'@'localhost' IDENTIFIED BY 'typo3';" | mysql -uroot -hlocalhost
echo "CREATE DATABASE typo3;" | mysql -uroot -hlocalhost
echo "GRANT ALL PRIVILEGES ON typo3.* TO 'typo3'@'localhost';" | mysql -uroot -hlocalhost

composer exec typo3cms install:setup -- \
    --no-interaction \
    --database-user-name=typo3 \
    --database-user-password=typo3 \
    --database-host-name=127.0.0.1 \
    --database-port=3306 \
    --database-name=typo3 \
    --use-existing-database \
    --admin-user-name=admin \
    --admin-password=password \
    --site-setup-type=site


mkdir -p public/typo3conf/ext || :
ln -s /opt/fal_manja public/typo3conf/ext/fal_manja

TYPO3_CONTEXT=Development php -S 0.0.0.0:8000 -t public

```

