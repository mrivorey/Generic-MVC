<?php
namespace App\Controllers;

class HomeController extends BaseController
{
    public function index(): string
    {
        return $this->view('home/index', [
            'authenticated' => $this->isAuthenticated(),
            'username' => $this->getSessionUser(),
        ]);
    }
}
