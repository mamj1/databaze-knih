<?php
  /** @var \PDO $db - připojení k databázi */
  $db = new PDO('mysql:host=127.0.0.1;dbname=mamj01;charset=utf8', 'mamj01', 'heslo-databaze');

  //při chybě v SQL chceme vyhodit Exception
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);