Form Filter for Silex
---------------------

To see the complete documentation, check out [lexik/LexikFormFilterBundle](https://github.com/lexik/LexikFormFilterBundle)

Install
-------
```bash
composer require inbep/form-filter-service-provider
```

```php
use Inbep\Silex\Provider\FormFilterServiceProvider;

$app->register(new FormFilterServiceProvider());

$form = $app['form.factory']
    ->createBuilder(/.../)
    ->getForm()
    ->handleRequest($req);

$query = /.../;

$app['form_filter']->addFilterConditions($form, $query);
```

License
-------
MIT
