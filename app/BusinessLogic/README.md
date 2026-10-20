# Business logic

Create a class with `php jeep make:logic RegisterCustomer`. Put reusable application rules and workflows here. Keep controllers focused on request and response handling, and call a business-logic class for work that spans models or needs reuse.

```php
$registration = new App\BusinessLogic\RegisterCustomer();
$registration->handle();
```
