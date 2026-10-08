<?php

declare(strict_types=1);

namespace App\Command\Enterprise;

use App\Dto\Enterprise\Input\UpdateEnterpriseInput;
use App\Exception\Business\BusinessException;
use App\Service\Enterprise\GetEnterpriseService;
use App\Service\Enterprise\UpdateEnterpriseService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:enterprise:configure', description: 'Cadastrar a empresa da instalação.')]
final class ConfigureEnterpriseCommand extends Command
{
    public function __construct(private GetEnterpriseService $current, private UpdateEnterpriseService $service, private ValidatorInterface $validator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('if-missing', null, InputOption::VALUE_NONE, 'Preservar empresa já cadastrada.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $onlyIfMissing = true === $input->getOption('if-missing');

        if (true === $onlyIfMissing && true === $this->current->get()->configured) {
            $io->success('Empresa já cadastrada; dados preservados.');

            return Command::SUCCESS;
        }

        $enterprise = new UpdateEnterpriseInput(
            legalName: trim((string) $io->ask('Razão social')),
            tradeName: trim((string) $io->ask('Nome fantasia')),
            cnpj: trim((string) $io->ask('CNPJ (opcional)', '')),
            email: trim((string) $io->ask('Email (opcional)', '')),
            phone: trim((string) $io->ask('Telefone (opcional)', '')),
            address: trim((string) $io->ask('Endereço (opcional)', '')),
            commissionPrefix: strtoupper(trim((string) $io->ask('Prefixo das comissões', 'GCOM'))),
        );
        $violations = $this->validator->validate($enterprise);

        if (0 < count($violations)) {
            throw new BusinessException((string) $violations[0]->getMessage());
        }

        $this->service->update($enterprise, null, $onlyIfMissing);
        $io->success('Empresa cadastrada.');

        return Command::SUCCESS;
    }
}
