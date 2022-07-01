# variante für version 11.5 im non-composermode

FAL_MANJA=$PWD
docker kill fal_manja
docker run --rm -it  -d -p 55080:8000 -v $FAL_MANJA:/opt/fal_manja --name fal_manja git.manjadigital.de:4567/manja/manja_container/base-webrtenv:debian-10
docker exec -it fal_manja bash


# in container:
apt update && apt install imagemagick 

sudo apt install wget

sudo apt install apt-transport-https lsb-release ca-certificates -y
sudo wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg 
sudo sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
sudo apt update
dpkg -l | grep php | tee packages.txt
sudo apt install php7.4 php7.4-common php7.4-cli
sudo apt install php7.4-curl php7.4-mbstring php7.4-bz2 php7.4-readline php7.4-intl
sudo apt install php7.4-bcmath php7.4-bz2 php7.4-curl php7.4-intl php7.4-mbstring php7.4-mysql php7.4-readline php7.4-xml php7.4-zip
sudo apt purge php7.3 php7.3-common
sudo apt install php-xml  php7.4-mysql default-mysql-server
sudo apt install postgresql postgresql-contrib
pg_ctlcluster 11 main start
apt install php-pgsql

sudo -u postgres psql -c "CREATE ROLE typo3 WITH LOGIN INHERIT CONNECTION LIMIT -1 PASSWORD 'typo3';" 
sudo -u postgres psql -c "CREATE DATABASE typo3 WITH OWNER = typo3 ENCODING = 'UTF8' CONNECTION LIMIT = -1;"

curl -L -o typo3_src.tgz https://get.typo3.org/11.5.12
gunzip typo3_src.tgz
tar -xf typo3_src.tar
cd typo3_src-11.5.12
touch FIRST_INSTALL

TYPO3_CONTEXT=Development php -S 0.0.0.0:8000 -t .

ln -s /opt/fal_manja typo3conf/ext/fal_manja

TYPO3_CONTEXT=Development php -S 0.0.0.0:8000 -t .
