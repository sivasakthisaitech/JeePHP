// JeePHP package: RolesO
route('/roles', 'RolesController@index');
route('POST /roles/create', 'RolesController@storeRole');
route('POST /roles/permissions/create', 'RolesController@storePermission');
route('POST /roles/grant', 'RolesController@grant');
