# Page web du mini-SI (examen-proxmox-3SCR2)

Page PHP unique, sans dépendance externe. Elle affiche le nom d'hôte et les adresses de l'invité web, l'IP du client, l'heure du rendu, puis l'état de la connexion à l'invité BDD (OK + version du moteur + nom d'hôte de la BDD, ou KO + message d'erreur).

Équipe : Allen, Oumar, Vincent · classe 3SCR2.

## Fichiers

| Fichier | Rôle |
| --- | --- |
| `index.php` | La page |
| `config.example.php` | Paramètres de connexion d'exemple |
| `config.php` | Paramètres réels, à créer, ignoré par git |

`index.php` charge `config.php` s'il existe, sinon `config.example.php`. Le fichier utilisé est indiqué en bas de page.

## Déploiement sur l'invité web (Debian)

Paquets, selon le serveur web retenu :

```sh
# Apache
apt install apache2 libapache2-mod-php php-mysql

# ou nginx
apt install nginx php-fpm php-mysql
```

Copie des fichiers :

```sh
cp index.php config.example.php /var/www/html/
cd /var/www/html
cp config.example.php config.php
# éditer config.php : host, port, dbname, user, password
chown root:www-data config.php && chmod 640 config.php
rm -f index.html                # page par défaut d'Apache
rm -f index.nginx-debian.html   # page par défaut de nginx
```

### nginx

Dans `/etc/nginx/sites-available/default`, à l'intérieur du bloc `server { }` :

```nginx
index index.php index.html;

location ~ /config(\.example)?\.php$ {
    deny all;
}

location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
```

La conf complète utilisée sur `ct-web` est dans [`../nginx/default`](../nginx/default) : il suffit de la copier dans `/etc/nginx/sites-available/default`.

`php-fpm.sock` est un lien générique fourni par Debian vers le socket de la version installée (`php8.4-fpm.sock` sur Debian 13) : la conf reste valable après une montée de version de PHP. Si on part du fichier par défaut de Debian, garder **une seule** ligne `fastcgi_pass` (celle du socket). Le bloc `deny all` doit venir avant lui, car nginx retient la première regex qui correspond. Ce `deny all` sur les fichiers de configuration empêche nginx de servir `config.php` en clair si PHP-FPM n'est pas branché. `index.php` les charge depuis le disque, il n'est donc pas concerné. Recharger ensuite avec `nginx -t && systemctl reload nginx`.

### Apache

`libapache2-mod-php` exécute les `.php` sans autre réglage. Même précaution que pour nginx, dans le bloc `<VirtualHost>` de `/etc/apache2/sites-available/000-default.conf` :

```apache
<FilesMatch "^config(\.example)?\.php$">
    Require all denied
</FilesMatch>
```

Recharger ensuite avec `apachectl configtest && systemctl reload apache2`.

### Vérification

Ouvrir `http://<ip de l'invité web>/` depuis le poste hôte.

## Base et utilisateur côté MariaDB (invité BDD)

```sql
CREATE DATABASE minisi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'minisi_app'@'<ip web>' IDENTIFIED BY '<mot de passe>';
GRANT SELECT ON minisi.* TO 'minisi_app'@'<ip web>';
```

`<ip web>` est l'adresse de l'invité web sur le bridge interne.

Sous Debian, MariaDB n'écoute par défaut que sur `127.0.0.1`. Il faut régler `bind-address` sur l'IP du bridge interne dans `/etc/mysql/mariadb.conf.d/50-server.cnf`, puis lancer `systemctl restart mariadb`.

## Test rapide

```sh
php -l index.php
php -S 127.0.0.1:8099 -t .   # puis http://127.0.0.1:8099/
```

Si la base est injoignable ou si `php-mysql` manque, la page s'affiche quand même avec un statut KO.
