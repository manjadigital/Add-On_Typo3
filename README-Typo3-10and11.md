



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


