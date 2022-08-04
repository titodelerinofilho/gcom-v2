<?php

ini_set('display_errors', 1);

include '../vendor/autoload.php';

use App\Controller\CategoryController;
use App\Controller\ProductController;
use App\Controller\ProductApiController;
use App\Controller\ErrorController;
use App\Controller\IndexController;
use App\Controller\UserController;
use App\Controller\AuthController;
use App\Security\UserSecurity;

$url = explode('?', $_SERVER['REQUEST_URI'])[0];

session_start();

/*if (UserSecurity::getUser()) {
    match($url) {
        '/' => (new IndexController)->home(),
        '/categorias' => (new CategoryController())->list(),
        '/categorias/pdf' => (new CategoryController())->pdf(),
        '/listar-produtos' => (new ProductController())->list(),
        '/produtos/excluir' => (new ProductController())->remove(),
        '/listar-usuarios' => (new UserController)->list(),
        '/novo-categoria' => (new CategoryController())->add(),
        '/novo-produto' => (new ProductController())->add(),
        '/usuarios/novo' => (new UserController())->add(),
        '/usuarios/ativar' => (new UserController())->enable(),
        '/usuarios/desativar' => (new UserController())->disable(),
        '/sair' => (new AuthController())->logout(),
        default => (new ErrorController())->notFound(),
    };
} else {
    match($url) {
        '/' => (new IndexController)->home(),
        '/api/produtos' => (new ProductApiController())->getAll(),
        '/login' => (new AuthController())->login(),
        default => (new ErrorController())->notFound(),
    };
}
*/