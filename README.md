WebITL780
INFINTY ITL780 - это веб-приложение для управления и настройки устройств, написанное на PHP и JavaScript.

Содержание

Требования
Установка
Структура проекта
Использование


Требования

Веб-сервер (lighttpd)
PHP 8.1.4 или выше


Установка


Клонируйте репозиторий:

git clone http://10.10.2.11/cuman/webitl780.git
cd webitl780




Для запуска локально убедитесь что все конфигурационные файлы :


Скопируйте файлы проекта в корневую директорию вашего веб-сервера.

/opt/cumanphone/etc/config.conf
/etc/systemd/network/80-lan0.network
/etc/default/ntpdate
/opt/cumanphone/share/blf/blf.conf



Проверьте php/config.php



// Для запуска локально

$networkd_filename = "../config/80-lan0.network";
$config_filename = "../config/config.conf";
$ntpdate_filename = "../config/ntpdate";
$blf_filename = "../config/blf.conf";

// Для запуска на телефоне

// $networkd_filename = "/etc/systemd/network/80-lan0.network";
// $config_filename = "/opt/cumanphone/etc/config.conf";
// $ntpdate_filename = "/etc/default/ntpdate";
// $blf_filename = "/opt/cumanphone/share/blf/blf.conf";




Запустите ваш веб-сервер с помощью php на 8000 порту

php -S 0.0.0.0:8000





Структура проекта

.
├── api_config ----------------------- Директория в котором хранятся динамичные статусы 
│   ├── account_statuses.json 
│   └── download_status.json
├── assets
│   ├── bootstrap
│   ├── css
│   ├── fonts
│   ├── img
│   └── js --- dialog.js который отвечает за отображение модального окна после сохранения
├── blf.html
├── calls.html
├── codecs.html
├── common.html
├── config/  ------- Конфигурационные файлы для запуска локально
├── contacts.html
├── index.html
├── language.html
├── languages ------------ Переводы страниц
│   ├── calls.json
│   ├── codec.json
│   ├── common.json
│   ├── contacts.json
│   ├── download_types.json
│   ├── index.json
│   ├── lang_page.json
│   ├── network.json
│   ├── redirect.json
│   ├── security.json
│   ├── show_dialog.json
│   ├── show_notcomplete_dialog.json
│   ├── show_reboot_dialog.json
│   ├── show_reset_dialog.json
│   ├── show_restart_dialog.json
│   ├── sip.json
│   ├── status.json
│   └── system.json
├── network.html
├── php
│   ├── account_statuses.php ---------- Принимает и отправляет информацию про вбонентов
│   ├── blf.php
│   ├── calls.php
│   ├── common.php
│   ├── config.php
│   ├── contacts.php
│   ├── download_status.php ---------- Принимает и отправляет статус загрузки контактов
│   ├── language.php ---------- Принимает и отправляет переводы страниц
│   ├── login.php
│   ├── network.php
│   ├── redirect.php
│   ├── security.php
│   ├── sip.php
│   ├── status.php
│   ├── system.php
│   └── upload.php
├── README.md
├── redirect.html
├── security.html
├── sip.html
├── status.html
└── system.html




Использование

При отправке на релиз:

Скопируйте содержимое проекта в новую директорию www

Убедитесь что закоментировали строчки для запуска локально
Удалите:

.git
README.md
config/
.gitignore


Сжавь директорию www отправьте www.zip