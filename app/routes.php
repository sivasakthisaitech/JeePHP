<?php
declare(strict_types=1);

url_remove('.php');

route('/', 'HomeController@index');
route('/contact', 'ContactController@index');
route('/user/:id', 'UserController@show');
route('POST /save', 'PostController@store');
