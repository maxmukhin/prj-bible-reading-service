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
            ->defaults()
            ->autowire()
            ->autoconfigure();

        // 1. Загружаем все сервисы (Репозитории, UseCases, Презентеры) как приватные
        $services->load('App\\', '../src/*')
            ->exclude([
                '../src/Domain/Model',
                '../src/Presentation/Controller', // Исключаем контроллеры отсюда
                '../src/Kernel.php',
            ]);

        // 2. Загружаем контроллеры отдельно, автоматически помечая их правильным тегом
        $services->load('App\\Presentation\\Controller\\', '../src/Presentation/Controller/*')
            ->tag('controller.service_arguments')
            ->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/Presentation/Controller/', 'attribute');
    }
}

