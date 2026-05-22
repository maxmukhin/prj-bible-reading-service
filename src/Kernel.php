<?php
// src/Kernel.php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => $_ENV['APP_SECRET'] ?? '67f0fa236d8d32d0d0c67993a4a8db21',
            'router' => ['utf8' => true],
            'session' => [
                'storage_factory_id' => 'session.storage.factory.native',
                'cookie_samesite' => 'lax',
                'cookie_secure' => 'auto',
            ]
        ]);

        $services = $container->services()
            ->defaults()->autowire()->autoconfigure();

        // 1. Регистрируем наши конкретные классы
        $services->set(\App\Infrastructure\Persistence\SqliteUserRepository::class);
        $services->set(\App\Application\UseCase\RegisterUserUseCase::class);
        $services->set(\App\Presentation\Controller\AuthController::class)->public();
        $services->set(\App\Infrastructure\Persistence\SqliteNoteRepository::class);
        $services->set(\App\Application\UseCase\CreateNoteUseCase::class);
        $services->set(\App\Infrastructure\Persistence\SqliteBibleRepository::class);
        $services->set(\App\Presentation\Controller\BibleController::class)->public();

        $services->set(\App\Presentation\Controller\HtmxNoteController::class)->public();
        $services->set(\App\Application\UseCase\UpdateNoteUseCase::class);

        $services->set(\App\Infrastructure\Persistence\SqliteFriendshipRepository::class);
        $services->set(\App\Presentation\View\BibleHtmlRenderer::class);
        $services->set(\App\Presentation\Controller\HtmxFriendController::class)->public();

        $services->set(\App\Application\UseCase\SaveNoteUseCase::class);


        //
        //$services->set(\App\Presentation\ApiController\ApiNoteController::class)->public();

        $services->alias(
            \App\Domain\Repository\BibleRepositoryInterface::class,
            \App\Infrastructure\Persistence\SqliteBibleRepository::class
        );

        $services->alias(
            \App\Domain\Repository\NoteRepositoryInterface::class,
            \App\Infrastructure\Persistence\SqliteNoteRepository::class
        );

        $services->alias(
            \App\Domain\Repository\UserRepositoryInterface::class,
            \App\Infrastructure\Persistence\SqliteUserRepository::class
        );
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/Presentation/Controller/', 'attribute');
    }
}

