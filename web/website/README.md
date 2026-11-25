# Page web du mini-SI (examen-proxmox-3SCR2)

Page PHP unique (`index.php`) : nom d'hôte, IP du serveur, date, et état de la connexion à la base (OK + nom d'hôte de la BDD, ou KO). Équipe : Allen, Oumar, Vincent, 3SCR2.

`index.php` charge `config.php` s'il existe, sinon `config.example.php`.

## Déploiement sur ct-web (Debian, nginx)

```sh
apt install nginx php-fpm php-mysql
cp index.php config.example.php /var/www/html/
cd /var/www/html
rm -f index.nginx-debian.html
cp config.example.php config.php   # puis éditer host, port, dbname, user, password
chown root:www-data config.php && chmod 640 config.php
cp <depot>/web/nginx/default /etc/nginx/sites-available/default
nginx -t && systemctl reload nginx
systemctl restart php8.4-fpm       # optionnel, après l'install de php-mysql
```

Ouvrir ensuite `http://172.30.0.100/`.

## Base de données (ct-db, MariaDB)

```sql
CREATE DATABASE minisi;
CREATE USER 'minisi_app'@'<ip web interne>' IDENTIFIED BY '<mot de passe>';
GRANT SELECT ON minisi.* TO 'minisi_app'@'<ip web interne>';
```

Dans `/etc/mysql/mariadb.conf.d/50-server.cnf`, mettre `bind-address` à l'IP interne de ct-db, puis `systemctl restart mariadb`.

## Diagnostic si « KO »

La page n'affiche aucun détail. La cause est dans le log d'erreurs nginx, lignes `minisi:` :

```sh
grep minisi /var/log/nginx/error.log
tail -f /var/log/nginx/error.log
```
