// JeePHP package: AuthO
route('/auth/register', 'AuthController@register');
route('/auth/login', 'AuthController@login');
route('/auth/account', 'AuthController@account');
route('POST /auth/logout', 'AuthController@logout');
