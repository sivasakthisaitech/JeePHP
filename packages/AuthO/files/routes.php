// JeePHP package: AuthO
route('/auth/register', 'AuthController@register');
route('POST /auth/register', 'AuthController@register');
route('/auth/login', 'AuthController@login');
route('POST /auth/login', 'AuthController@login');
route('/auth/account', 'AuthController@account');
route('POST /auth/logout', 'AuthController@logout');
route('/auth/password/forgot', 'AuthController@forgotPassword');
route('POST /auth/password/forgot', 'AuthController@forgotPassword');
route('/auth/password/reset/:token', 'AuthController@resetPassword');
route('POST /auth/password/reset/:token', 'AuthController@resetPassword');
route('/auth/email/verify/:token', 'AuthController@verifyEmail');
