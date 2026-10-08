<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Dto\User\Input\CreateUserInput;
use App\Exception\Business\BusinessException;
use App\Service\User\CreateUserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:user:create-admin', description: 'Criar administrador com senha informada de forma interativa.')]
final class CreateAdminCommand extends Command
{
    public function __construct(private CreateUserService $users, private ValidatorInterface $validator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $io->ask('Nome');
        $email = $io->ask('Email');
        $password = $io->askHidden('Senha (mínimo 12 caracteres)');

        $userInput = new CreateUserInput((string) $name, (string) $email, ['ROLE_ADMIN'], true, (string) $password);
        $violations = $this->validator->validate($userInput, groups: ['Default', 'create']);

        if (0 < count($violations)) {
            throw new BusinessException((string) $violations[0]->getMessage());
        }

        $this->users->create($userInput, null);

        $io->success('Administrador criado.');

        return Command::SUCCESS;
    }
}
