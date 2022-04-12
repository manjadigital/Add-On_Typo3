



### Buld

```sh
docker run --rm -it --mount \
  type=bind,source=/home/robert/projects/Add-On_Typo3,target=/opt/source \
  git.manjadigital.de:4567/manja/manja_container/base-buildenv:debian-10 \
  bash -c 'cd /opt/source; composer install --no-interaction --no-progress --ignore-platform-reqs --optimize-autoloader --classmap-authoritative'
```



