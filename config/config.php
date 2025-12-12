<?php
// config/config.php
session_start();

// Настройки БД
define('DB_HOST', 'localhost');
define('DB_NAME', 'cinema');
define('DB_USER', 'root');
define('DB_PASS', '44164'); // поменяй под себя

// Базовый URL проекта (если папка в корне — '/cinema')
define('BASE_URL', '/cinema');
