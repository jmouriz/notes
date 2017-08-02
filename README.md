# Notes

Notes es un pequeño cuaderno de apuntes escrito en PHP que usa SQLite como base de datos. Es más bien un ejemplo de una aplicación AngularJS con diseño de materiales, sensible al dispositivo (responsive) aunque por estar escrita en PHP no es del todo híbrida. La idea principal es que toda la aplicación se encuentre en un sólo archivo.

## Instalación

```
bower install
composer install
sqlite3 notes.db
sql> create table notes (
...>   id integer primary key,
...>   date timestamp,
...>   title varchar(120),
...>   detail text
...>);
sql> .quit
chmod g+w notes.db
chgrp www-data notes.db
```
