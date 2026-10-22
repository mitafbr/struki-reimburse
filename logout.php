<?php
require 'config.php';
$_SESSION = [];
session_destroy();
session_start();
flash('Kamu sudah keluar. Sampai jumpa.');
redirect('login.php');
