!!!!!!!BARDZO WAŻNE!!!!!!!
Kroki uruchomienia aplikacji:
git clone https://github.com/eryksowacki/project_programowanie_zaawansowane.git
<<<<<<< HEAD
cd project_programowanie_zaawansowane
Edycja pliku C:\xampp\php\php.ini -> odkomentować linie ‚extension=gd’ i ‚extension-zip’
W katalogu projektu:
composer install

W katalogu: 
C:\xampp\apache\conf\extra
Edytować plik https-vhosts.conf, dodając na końcu: <VirtualHost *:80>
    ServerName docledger.local
    DocumentRoot "C:/xampp/htdocs/project_programowanie_zaawansowane/public"
=======

cd project_programowanie_zaawansowane

Edycja pliku C:\xampp\php\php.ini -> odkomentować linie ‚extension=gd’ i ‚extension-zip’

W katalogu projektu:
composer install

W katalogu:
C:\xampp\apache\conf\extra
Edytować plik https-vhosts.conf, dodając na końcu:<VirtualHost \*:80>
ServerName docledger.local
DocumentRoot "C:/xampp/htdocs/project_programowanie_zaawansowane/public"
>>>>>>> f199f07 (Final commit v3)

    <Directory "C:/xampp/htdocs/project_programowanie_zaawansowane/public">
        AllowOverride All
        Require all granted
    </Directory>
<<<<<<< HEAD
=======

>>>>>>> f199f07 (Final commit v3)
</VirtualHost>

Włączamy xampp

php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -> yes
php bin/console doctrine:fixtures:load --env=dev -> yes

Reload xampp

<<<<<<< HEAD
W przeglądarce:
http://localhost/login

Jeśli powyższe nie zadziała to należy edytować plik C:\Windows\System32\drivers\etc\hosts, dodając wpis: 
=======
W przeglądarce: http://localhost/login

Jeśli powyższe nie zadziała to należy edytować plik C:\Windows\System32\drivers\etc\hosts, dodając wpis:
>>>>>>> f199f07 (Final commit v3)
127.0.0.1 docledger.local
Na końcu pliku, wtedy w przeglądarce:

docledger.local
<<<<<<< HEAD


=======
>>>>>>> f199f07 (Final commit v3)
