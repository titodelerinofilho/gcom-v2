<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Adjustment;
use App\Entity\User;
use App\Exception\BusinessException;
use App\Integration\Winthor\MovementGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ReturnImporter
{
    public function __construct(private MovementGatewayInterface $movements, private ReturnCalculator $calculator, private CommissionRules $rules, private EntityManagerInterface $em, private AuditRecorder $audit)
    {
    }

    public function import(string $customer, string $numtransent, bool $atg, User $actor): Adjustment
    {
        $snapshot = $this->calculator->calculate($this->movements->returns($customer, $atg, $numtransent), $this->rules->current(), $atg);
        $principals = array_unique(array_map(static fn (array $row): string => (string) $row['PRINCIPAL'], $snapshot['sourceRows']));

        if (1 !== count($principals)) {
            throw new BusinessException('A devolução deve pertencer a um único cliente principal.');
        }
        $source = 'winthor:return:'.$numtransent;

        return $this->em->wrapInTransaction(function () use ($snapshot, $principals, $source, $numtransent, $actor): Adjustment {
            $lockSql = 'SELECT pg_advisory_xact_lock(hashtextextended(:source, 0))';
            $this->em->getConnection()->executeQuery($lockSql, ['source' => 'gcom:customer:'.reset($principals)]);
            foreach ($snapshot['items'] as $line) {
                if ($this->em->getRepository(Adjustment::class)->findOneBy(['sourceKey' => 'winthor:cancellation:'.$line['orderNumber']])) {
                    throw new BusinessException('Pedido já estornado por cancelamento. Não registre uma segunda dedução.', 409);
                }
            }

            $this->em->getConnection()->executeQuery($lockSql, ['source' => $source]);

            if ($this->em->getRepository(Adjustment::class)->findOneBy(['sourceKey' => $source])) {
                throw new BusinessException('NUMTRANSENT já registrado. A devolução não pode ser deduzida duas vezes.', 409);
            }
            $adjustment = (new Adjustment())->setCustomerCode(reset($principals))->setType('return')->setAmount($snapshot['amount'])
                ->setReason('Devolução Winthor calculada pela diferença entre PUNIT e PTABELA1 PSD.')->setSourceReference('NUMTRANSENT '.$numtransent)->setCreatedBy($actor)
                ->captureSource($source, $snapshot);
            $this->em->persist($adjustment);
            $this->audit->record($actor, 'return.imported', $source, ['amount' => $snapshot['amount'], 'ruleVersion' => $snapshot['rule']['version'], 'items' => count($snapshot['items'])]);

            return $adjustment;
        });
    }
}
