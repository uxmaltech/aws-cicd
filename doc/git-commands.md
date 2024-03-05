
config/uxmaltech.php
```php
<?php
return [
    'git' => [
        'repositories' => [
            'workshop-backoffice' => base_path('.'),
            'workshop-backend' => base_path('../workshop-backend'),
            'workshop-core' => base_path('../workshop-core'),
        ]
    ]
];
```

php artisan git:create-branch branch-name

Comando para crear una rama en todos los repositorios configurados en el archivo de configuración uxmaltech.php
