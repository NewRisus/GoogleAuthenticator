# Autenticador de doble factor (2FA)

[![GitHub license](https://img.shields.io/github/license/NewRisus/GoogleAuthenticator?style=flat-square)](https://github.com/NewRisus/GoogleAuthenticator/blob/main/LICENSE)
[![GitHub issues](https://img.shields.io/github/issues/NewRisus/GoogleAuthenticator?style=flat-square)](https://github.com/NewRisus/GoogleAuthenticator/issues)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/NewRisus/GoogleAuthenticator?label=Peso%20total&style=flat-square)
![GitHub language count](https://img.shields.io/github/languages/count/NewRisus/GoogleAuthenticator?label=Leguajes&style=flat-square)

![Discord](https://img.shields.io/discord/631217084411543582?label=Servidor%20Discord&style=flat-square)
![GitHub all releases](https://img.shields.io/github/downloads/NewRisus/GoogleAuthenticator/total?label=Descargas&style=flat-square)

> Estos son los pasos para instalarlo, descargan la carpeta y la suben a inc/ext/

1 - Deben ejecutar esta consulta
```sql
ALTER TABLE u_miembros ADD user_doublefactor varchar(18) NOT NULL DEFAULT '';
```

2 - En este caso lo he agregado a **inc/php/class/_c.cuenta.php_**, arriba de
```php
function savePerfil(){
```

agregan lo siguiente
```php
# Para activar el doble factor
function TwoFactor($secreto, $id) {
  # Clave secreta
  if(db_exec(array(__FILE__, __LINE__), 'query', "UPDATE u_miembros SET user_doublefactor = '{$secreto}' WHERE user_id = {$id}")) return '1: 2FA activado correctamente.';
  else return '0: No se pudo activar 2FA.';
}
# Para desactivar el doble factor
function delTwoFactor() {
  global $tsUser;
  $update = "UPDATE u_miembros SET user_doublefactor = '' WHERE user_id = {$tsUser->uid}";
  if(db_exec(array(__FILE__, __LINE__), 'query', $update)) return '1: Ha sido desactivado correctamente.';
  else '0: Hubo un problema al querer desactivar.';
}
```

3 - En **.htaccess** buscamos 
```
RewriteRule ^cuenta.php$ inc/php/cuenta.php [QSA,L]
```
y debajo agregamos
```
RewriteRule ^cuenta/([A-Za-z0-9_-]+)$ inc/php/cuenta.php?accion=$1 [QSA,L]
```

4 - Luego en **inc/php/_cuenta.php_** debajo de 
```php
$action = $_GET['action'];
```
agregan
```php
$action2 = $_GET['accion'];
```
un poco más abajo buscan
```php
$tsCuenta = new tsCuenta();
```
agregan lo siguiente
```php
# Segundo factor de autentificado
if($action2 == 'doble_factor') {
  # Incluimos el archivo
  include TS_EXTRA . "GoogleAuthenticator/GoogleAuthenticatorInit.php";
  # Crearemos un arreglo para enviar
  $user = array(
    'name' => $tsUser->nick, 
    'email' => $tsUser->info['user_email'], 
    'password' => $tsUser->info['user_password'],
   'two_factor_key' => $tsUser->info['user_doublefactor']
  );
  # Si esta activado
  $hasTwoFactorActive = TRUE;
  # Comprobamos que el usuario no tenga el token de 2FA
  if(empty($user['two_factor_key'])) {
    $hasTwoFactorActive = FALSE;
    $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
    $secret = $g->generateSecret();
    # Acá creará el código QR que necesitarás para activar 
    $qrCode = \Sonata\GoogleAuthenticator\GoogleQrUrl::generate($user['email'], $secret, $tsCore->settings['titulo'], 250);
    $smarty->assign("tsGoogleAuthenticator", $qrCode);
    $smarty->assign("tsSecret", $secret);
  }
  $smarty->assign("tsG2FA", $hasTwoFactorActive);
}
$smarty->assign('tsAccion', $action2);
``` 
5 - Vamos a inc/php/ajax/ y crearemos un archivo llamado **_ajax.cuenta.php_** y en el agregamos lo siguiente
https://gist.github.com/joelmiguelvalente/d8bd7d72d5cfafbb1dfa10fb8497eddd

6 - En inc/class/c.user.php y buscamos dentro de la función
```php
function loginUser($username, $password, $remember = FALSE, $redirectTo = NULL){
``` 
lo siguiente:
* A lado de `user_password,` agregamos `user_doublefactor,`
* debajo de 
```php
    $this->session->update($data['user_id'], $remember, TRUE);
``` 
  y lo reemplazamos por
```php
    # Si no tiene el 2fa activado el login funcionará normal
    if(empty($data['user_doublefactor'])):
       # Si tiene 2fa activado esto no creará la sesión, ya que tenemos que comprobar
       $this->session->update($data['user_id'], $remember, TRUE);
    endif;
``` 
* un poco más abajo buscamos 
```php
    if($redirectTo != NULL) $tsCore->redirectTo($redirectTo);	// REDIRIGIR
    else return TRUE;
``` 
  y lo reemplazamos por
```php
    if(empty($data['user_doublefactor'])):
       if($redirectTo != NULL): 
          $tsCore->redirectTo($redirectTo);
       else:
          return TRUE;
       endif;
    else:
	     return '2: Ingresamos los 6 dígitos.';
    endif;
``` 
7 - Abajo o arriba de la misma `function loginUser(...) { }` agregan 
```php
function validateTwoFactor($g) {
  global $tsCore;
  # Buscamos el token para el acceso del usuario
  $data = db_exec('fetch_assoc', db_exec(array(__FILE__, __LINE__), 'query', "SELECT user_id, user_doublefactor FROM u_miembros WHERE user_name = '{$_POST['user']}'"));
  # En el caso que sea correctro creará la sesión adecuada
  if($g->checkCode($data['user_doublefactor'], $_POST['code'])) {
     $this->session->update($data['user_id'], $_POST['rem'], TRUE);
     return true;
  } 
  # Si es incorrecto, pues, no hará nada y no se logueará
  return false;		
}
``` 
8 - En themes/TU-TEMA/js/**acciones.js** buscan en: `function login_ajax(){`
```js
case '2':
	$('.login').css('text-align', 'center').css('line-height', '150%').html(h.substring(3));
break;
``` 
y lo reemplazamos por (Obviamente deben adaptarlo a su diseño, esta con bootstrap 5)
```js
case '2':
  mydialog.show(true);
  mydialog.title('Doble factor');
  mydialog.body("<div class=\"form-floating\"><input type=\"text\" class=\"form-control shadow\" placeholder=\" \" name=\"code\" required><label for=\"usuario\">Código de autentificación</label><div class=\"text-secondary pt-1 fst-italic small\">Abra la aplicación de autenticación de dos factores en su dispositivo para ver su código de autenticación y verificar su identidad.</div></div>");
  mydialog.buttons(true, true, 'Continuar', `javascript:twoFactor()`, true, true, true, 'Cancelar', 'close', true, false);		
  mydialog.center();
break;
```

9 - En el mismo archivo debajo de: **function login_ajax(){ ... CODIGO ...}** <- [la última llave] agregan
```js
function twoFactor() {
	input = 'code='+$('input[name=code]').val();
	input += '&user='+$('input[name=nick]').val();
	input += '&rem='+$('input[name=rem]').is(':checked');
	$.post(global_data.url + '/login-validate.php', input, function(rsp) {
		if(rsp == true) {
			location.href = '/';
		}
	})
}
```

10 - En themes/TU-TEMA/modules/ crean un archivo llamado **m.cuenta_doble_factor.tpl** y agregan lo siguiente
https://gist.github.com/joelmiguelvalente/a35d0e9daf27d7d3a18b15425352e0ee

11 - En **themes/TU-TEMA/_t.cuenta.tpl_** buscan 
```html
<li><a onclick="cuenta.chgtab(this)">Privacidad</a></li>
```
y abajo pegan
```html
<li><a href="{$tsConfig.url}/cuenta/doble_factor">Activar 2FA</a></li>
```

más abajo buscamos
```html
{include file='modules/m.cuenta_config.tpl'}
```

y agregamos, y así evitamos que se muestre en otras pestañas
```html
{if $tsAccion == 'doble_factor'}
	{include file='modules/m.cuenta_doble_factor.tpl'}
{/if}
```

Capturas:

![Imagen para activar](https://i.imgur.com/FDyPkMm.png)
![Imagen para desactivar](https://i.imgur.com/130sNFr.png)
