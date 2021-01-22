<?php

/**
 * Con este archivo cargamos los ficheros necesarios
 * para el funcionamiento del 2FA
*/

$Google_2FA = realpath(__DIR__);

include $Google_2FA . "/FixedBitNotation.php";

include $Google_2FA . "/GoogleAuthenticatorInterface.php";

include $Google_2FA . "/GoogleAuthenticator.php";

include $Google_2FA . "/GoogleQrUrl.php";