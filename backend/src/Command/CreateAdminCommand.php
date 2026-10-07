<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\UserManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:create-admin', description: 'Criar administrador com senha informada de forma interativa.')]
final class CreateAdminCommand extends Command
{
    public function __construct(private UserManager $users)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $io->ask('Nome');
        $email = $io->ask('Email');
        $password = $io->askHidden('Senha (mínimo 12 caracteres)');

        $this->users->save(
            new User(),
            ['name' => $name, 'email' => $email, 'password' => $password, 'roles' => ['ROLE_ADMIN']],
            null,
        );

        $io->success('Administrador criado.');

        return Command::SUCCESS;
    }
}
