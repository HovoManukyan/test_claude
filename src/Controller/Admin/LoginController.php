<?php

namespace App\Controller\Admin;

use Symfony\Component\Routing\Attribute\Route;

class LoginController
{
    #[Route('/login', name: 'api_admin_login', methods: ['POST'])]
    public function login(): void
    {
        throw new \LogicException('This method should not be called. Symfony Security handles this route.');
    }
}