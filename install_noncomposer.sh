sudo apt update && apt install -y imagemagick 
sudo apt install -y postgresql postgresql-contrib php-xml
pg_ctlcluster 13 main start
sudo apt install -y php-pgsql
sudo -u postgres psql -c "CREATE ROLE typo3 WITH LOGIN INHERIT CONNECTION LIMIT -1 PASSWORD 'typo3';" 
sudo -u postgres psql -c "CREATE DATABASE typo3 WITH OWNER = typo3 ENCODING = 'UTF8' CONNECTION LIMIT = -1;"
cd opt
curl -L -o typo3_src.tgz https://get.typo3.org/11.5.12
gunzip typo3_src.tgz
tar -xf typo3_src.tar
mv typo3_src-11.5.12 typo3
cd typo3
touch FIRST_INSTALL
mkdir typo3conf/ext
ln -s /opt/typo3_storage_connector typo3conf/ext/typo3_storage_connector
export XDEBUG_SESSION=XDEBUG_ECLIPSE
mkdir .vscode
echo '{  
	"version": "0.2.0",
	"configurations": [
		{
			"name": "PHP: XDebug",
			"type": "php",
			"request": "launch",
			"cwd": "${workspaceFolder}",
			"log": true,
			"port": 9002,
			"xdebugSettings": {
				"max_children": 500,
				"max_data": 4096,
				"show_hidden": 1
			},
			"pathMappings": {
				"/opt/typo3_storage_connector/" : "${workspaceFolder}/typo3conf/ext/typo3_storage_connector/"
			}
		},
	]
}' > .vscode/launch.json
TYPO3_CONTEXT=Development php -S 0.0.0.0:8000 -t .