



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

docker run --rm -it  -d -p 8000:8000 -v $FAL_MANJA:/opt/typo3_storage_connector --name fal_manja git.manjadigital.de:4567/manja/manja_container/base-webrtenv:debian-11
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


ln -s /opt/typo3_storage_connector public/typo3conf/ext/typo3_storage_connector

TYPO3_CONTEXT=Development php -S 0.0.0.0:8000 -t public

```


### Typo3 11

```sh

FAL_MANJA=$PWD
#FAL_MANJA=$PWD/public/typo3conf/ext/fal_manja

docker run --rm -it  -d -p 8000:8000 -v $FAL_MANJA:/opt/typo3_storage_connector --name fal_manja11 git.manjadigital.de:4567/manja/manja_container/base-webrtenv:debian-11
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
ln -s /opt/typo3_storage_connector public/typo3conf/ext/typo3_storage_connector

TYPO3_CONTEXT=Development php -S 0.0.0.0:8000 -t public

```

## Debugging PHP in VSCode 

### 1. Attach to running container ...

Select the `fal_manja` or `fal_manja11` container.

### 2. Open folder ...

`/opt/typo3/`

### 3. Install Extension `xdebug.php-debug`

### 4. Add File `.vscode/launch.json`:

```json
{
	"version": "0.2.0",
	"configurations": [
		{
			"name": "PHP: XDebug",
			"type": "php",
			"request": "launch",
			"cwd": "${workspaceFolder}/public",
			"log": true,
			"port": 9002,
			"xdebugSettings": {
				"max_children": 500,
				"max_data": 4096,
				"show_hidden": 1
			},
			"pathMappings": {
				"/opt/typo3_storage_connector/" : "${workspaceFolder}/public/typo3conf/ext/typo3_storage_connector/"
			}
		},
	]
}
```

### 5. Run Debug Configuration `PHP: XDebug`

### 6. Running Typo3 changes to

```sh
export XDEBUG_SESSION=XDEBUG_ECLIPSE

# as before:
TYPO3_CONTEXT=Development php -S 0.0.0.0:8000 -t public
```

