<?php

/*
 * Admin page routes, loaded by IpRestrictServiceProvider when ip-restrict.admin.enabled is true.
 * In scope: $router (Illuminate\Routing\Router), $admin (config ip-restrict.admin).
 */

$controller = 'Wave\SecurityIpRestrict\Http\Controllers\AdminController';
$write = isset($admin['write_middleware']) ? (array) $admin['write_middleware'] : array();

$router->group(array(
    'prefix' => isset($admin['path']) ? $admin['path'] : 'ip-restrict',
    'middleware' => isset($admin['middleware']) ? (array) $admin['middleware'] : array(),
), function ($router) use ($controller, $write) {
    $router->get('/', array('as' => 'ip-restrict.index', 'uses' => $controller.'@index'));
    $router->post('/', array('as' => 'ip-restrict.add', 'uses' => $controller.'@add', 'middleware' => $write));
    $router->post('/remove', array('as' => 'ip-restrict.remove', 'uses' => $controller.'@remove', 'middleware' => $write));
    $router->post('/restore', array('as' => 'ip-restrict.restore', 'uses' => $controller.'@restore', 'middleware' => $write));
    $router->post('/deny', array('as' => 'ip-restrict.deny', 'uses' => $controller.'@deny', 'middleware' => $write));
    $router->post('/undeny', array('as' => 'ip-restrict.undeny', 'uses' => $controller.'@undeny', 'middleware' => $write));
    $router->get('/attempts', array('as' => 'ip-restrict.attempts', 'uses' => $controller.'@attempts'));
});
