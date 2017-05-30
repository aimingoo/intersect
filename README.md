# Intersect - a CROS proxy

Intersect is a CROS proxy, fork and update from [stamat/corsica](https://github.com/stamat/corsica). The CORSica is a very simple HTTP CORS proxy written in PHP using cURL.



## Features

* simple HTTP CORS proxy, support get/post/option/...
* support https for POST, with a common certificate data from Mozilla
* private client_secret option for Github api, or other client secret/token
  * testcase for github oAuth
* some simple guards, please customize yourself
* content length recalculation



## Install and Usage

```bash
# clone project
> git clone https://github.com/aimingoo/intersect
> cd intersect

# update these options
> grep '^\$.* Option' intersect.php
$SIMPLE_DEFAULT_HOME = 'Api bridge by aimingoo.'; // Option
$DOMAIN_ACCEPT = 'aimingoo.github.io';    // Option - Your Github pages site
$ROOT_CERT = __DIR__ . "/cacert.pem"; // Option - if you have these
$PRIVATE_CLIENT_SECRET = 'xxxxxx...'; // Option - !MUST UPDATE!

# check and(or) update your .htaccess
> cat .htaccess
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ intersect.php [NC,L,QSA]

# upload to your web site (with .htaccess)
#	- if .htaccess no accept, then rename intersect.php to default.php please
# ...

# Done
```



## About the testcase

The testcase for github oAuth only. please create a Github oAuth Application, and got client\_id and client\_secret from github settings.

now, update the client\_secret in `intersect.php`, and change/pass the client\_id at `test/try.sh`:

```bash
# update and(or) config test/headers.sh
#	- reset all headers for your web site
# and, call try.sh
> cd test
> bash try.sh 'http://your-site.github.io' 'your client_id'
...
```

if run the `try.sh`，a browser will open, and you must login with github account, accept auth for your application, and browser redirect a new page (set 'Authorization callback URL' in your oAuth application in github settings) in the end.

you will get

>  _code=_**xxxxxxxxxxxxxxxx**

from the redirected page url query string/paraments, now copy the code string into shell console:

```bash
> bash try.sh 'http://your-site.github.io' 'your client_id'
Try gateway: your-site.github.io with http
-> Get code, open browser and pick the code from redirected url
-> Input code: 8aacaebde14c80bf3e17 ## <-- COPY TO HERE and PRESS ENTER
```

If test success, you will get a response from github with access_token:

```bash
<- Return access_token:
==========================
access_token=3054a20b15a5afed76d16d0ca3073df082cd2de2&scope=public_repo&token_type=bearer
==========================
Done.
```



## Other

* the proxy site need php with cURL support
* see [gh-oauth-server](https://github.com/imsun/gh-oauth-server) project by imsun, if you have a server and nodejs supported
* dont include client_secert in frontend pages