<?php
// src/Presentation/Controller/AuthController.php

namespace App\Presentation\Controller;

use App\Application\UseCase\RegisterUserUseCase;
use App\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class AuthController
{
    public function __construct(
        private RegisterUserUseCase $registerUseCase,
        private UserRepositoryInterface $userRepository
    ) {}

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(Request $request): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');
        $user = $userId ? $this->userRepository->findById($userId) : null;

        if ($user) {
            return $this->renderLayout("
                <h2>Добро пожаловать, {$user->getUsername()}!</h2>
                <p>Вы успешно авторизованы в Bible Reading Service.</p>
                <a href='/logout' style='color: #ff4d4d;'>Выйти из аккаунта</a>
            ", $user->getUsername());
        }

        return $this->renderLayout("
            <h2>Добро пожаловать в Bible Reading Service</h2>
            <p>Для начала работы, пожалуйста, авторизуйтесь или создайте аккаунт.</p>
            <div style='margin-top: 20px;'>
                <a href='/login' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Войти</a>
                <a href='/register' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Регистрация</a>
            </div>
        ");
    }

    #[Route('/register', name: 'auth_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            try {
                $this->registerUseCase->execute(
                    (string)$request->request->get('username'),
                    (string)$request->request->get('password')
                );
                return new RedirectResponse('/login');
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->renderLayout("
            <h2>Регистрация нового пользователя</h2>
            " . ($error ? "<p style='color:red;'>$error</p>" : "") . "
            <form method='POST' style='display: flex; flex-direction: column; max-width: 300px; gap: 10px;'>
                <input type='text' name='username' placeholder='Имя пользователя' required style='padding: 8px;'>
                <input type='password' name='password' placeholder='Пароль' required style='padding: 8px;'>
                <button type='submit' style='padding: 10px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;'>Создать аккаунт</button>
            </form>
            <p><a href='/login'>Уже есть аккаунт? Войти</a></p>
        ");
    }

    #[Route('/login', name: 'auth_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $username = (string)$request->request->get('username');
            $password = (string)$request->request->get('password');

            $user = $this->userRepository->findByUsername($username);

            if ($user && password_verify($password, $user->getPasswordHash())) {
                $session = $request->getSession();
                $session->set('user_id', $user->getId());
                return new RedirectResponse('/');
            }

            $error = "Неверное имя пользователя или пароль.";
        }

        return $this->renderLayout("
            <h2>Авторизация</h2>
            " . ($error ? "<p style='color:red;'>$error</p>" : "") . "
            <form method='POST' style='display: flex; flex-direction: column; max-width: 300px; gap: 10px;'>
                <input type='text' name='username' placeholder='Имя пользователя' required style='padding: 8px;'>
                <input type='password' name='password' placeholder='Пароль' required style='padding: 8px;'>
                <button type='submit' style='padding: 10px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;'>Войти</button>
            </form>
            <p><a href='/register'>Нет аккаунта? Зарегистрироваться</a></p>
        ");
    }

    #[Route('/logout', name: 'auth_logout', methods: ['GET'])]
    public function logout(Request $request): Response
    {
        $request->getSession()->remove('user_id');
        return new RedirectResponse('/');
    }

    /**
     * Быстрый базовый шаблонизатор внутри контроллера (0 оверхеда на рендеринг диска)
     */
    private function renderLayout(string $content, ?string $username = null): Response
    {
        $html = "
        <!DOCTYPE html>
        <html lang='ru'>
        <head>
            <meta charset='UTF-8'>
            <title>Bible Reading Service</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f4f6f9; color: #333; margin: 0; padding: 40px; }
                .container { max-width: 600px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin: 0 auto; }
                h2 { color: #111; margin-top: 0; }
                a { text-decoration: none; color: #007bff; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div style='text-align: right; font-size: 12px; color: #666; margin-bottom: 20px;'>
                    " . ($username ? "Вы вошли как: <strong>$username</strong>" : "Гость") . "
                </div>
                $content
            </div>
        </body>
        </html>";

        return new Response($html);
    }
}
