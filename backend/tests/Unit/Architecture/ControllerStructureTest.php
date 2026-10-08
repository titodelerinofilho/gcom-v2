<?php

declare(strict_types=1);

namespace App\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\Attribute\Route;

final class ControllerStructureTest extends TestCase
{
    public function testControllersAndServicesAreGroupedBySubject(): void
    {
        $source = dirname(__DIR__, 3).'/src';

        foreach (['Controller', 'Service', 'Entity', 'Repository', 'Exception', 'Event', 'Command', 'EventListener'] as $layer) {
            self::assertSame([], glob($source.'/'.$layer.'/*.php'), 'Classes must be grouped by subject in '.$layer.'.');
        }
    }

    public function testEveryControllerExposesExactlyOneInvokableRoute(): void
    {
        $directory = dirname(__DIR__, 3).'/src/Controller';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $controllers = 0;

        foreach ($files as $file) {
            if (false === $file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($directory) + 1, -4);
            $class = new ReflectionClass('App\\Controller\\'.str_replace('/', '\\', $relative));
            self::assertStringEndsWith('Controller', $class->getShortName());
            $methods = array_filter($class->getMethods(ReflectionMethod::IS_PUBLIC), static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class->getName() && false === $method->isConstructor());
            self::assertSame(['__invoke'], array_values(array_map(static fn (ReflectionMethod $method): string => $method->getName(), $methods)), $class->getName());
            self::assertCount(1, $class->getAttributes(Route::class), $class->getName());
            ++$controllers;
        }

        self::assertGreaterThan(0, $controllers);
    }

    public function testServicesDoNotDependOnPersistenceInfrastructure(): void
    {
        $directory = dirname(__DIR__, 3).'/src/Service';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if (false === $file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertStringNotContainsString('EntityManager', $source, $file->getPathname());
            self::assertDoesNotMatchRegularExpression('/->(?:persist|flush|wrapInTransaction|beginTransaction|commit|rollback|getConnection)\(/', $source, $file->getPathname());
        }
    }
}
