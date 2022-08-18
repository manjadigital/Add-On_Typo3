
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
jq '.repositories[0] = {"type": "path", "url": "./packages/*/"}' composer.json | sponge composer.json
jq '.scripts["typo3-cms-scripts"][1] = "typo3cms database:updateschema"' composer.json | sponge composer.json
mkdir packages
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
composer require manja/typo3-storage-connector
export XDEBUG_SESSION=XDEBUG_ECLIPSE
mkdir .vscode
echo '{  
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
				"/opt/typo3_storage_connector/" : "${workspaceFolder}/public/typo3conf/ext/fal_manja/"
			}
		},
	]
}' > .vscode/launch.json

TYPO3_CONTEXT=Development php -S 0.0.0.0:8000 -t public