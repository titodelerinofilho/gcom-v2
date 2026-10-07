<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommissionRule;
use App\Entity\User;
use App\Exception\BusinessException;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class CommissionRules
{
    public function __construct(private EntityManagerInterface $em, private AuditRecorder $audit, private AuthorizationCheckerInterface $authorization)
    {
    }

    public function current(): array
    {
        $rule = $this->em->getRepository(CommissionRule::class)->findOneBy([], ['id' => 'DESC']);

        if (!$rule) {
            throw new BusinessException('Configure a regra de comissão antes de gerar comissões.', 409);
        }

        return $rule->view();
    }

    public function history(int $page): array
    {
        $repo = $this->em->getRepository(CommissionRule::class);

        return ['items' => array_map(static fn (CommissionRule $rule) => $rule->view(), $repo->findBy([], ['id' => 'DESC'], 30, ($page - 1) * 30)), 'total' => $repo->count([]), 'page' => $page];
    }

    public function save(array $data, User $actor): array
    {
        if (!$this->authorization->isGranted('ROLE_ADMIN')) {
            throw new BusinessException('Somente administradores podem alterar a fórmula.', 403);
        }
        $percentage = Money::decimal(Input::text($data, 'percentage', 12), 4);

        if (BigDecimal::of($percentage)->isLessThanOrEqualTo(0) || BigDecimal::of($percentage)->isGreaterThan(100)) {
            throw new BusinessException('O percentual deve ser maior que zero e no máximo 100.');
        }
        $basis = Input::text($data, 'basis', 30);

        if (!in_array($basis, ['margin_psd', 'margin_table', 'sales'], true)) {
            throw new BusinessException('Base de cálculo inválida.');
        }
        $contexts = $data['priceContexts'] ?? null;

        if (!is_array($contexts) || !array_is_list($contexts) || !$contexts || count($contexts) > 200) {
            throw new BusinessException('Informe de 1 a 200 pareamentos de filial/tabela.');
        }
        $seen = [];
        foreach ($contexts as $context) {
            if (!is_array($context) || !isset($context['branch']) || !is_string($context['branch']) || !preg_match('/^(\*|[0-9]{1,10})$/D', $context['branch'])) {
                throw new BusinessException('Filial inválida no pareamento. Use * para todas as filiais.');
            }
            foreach (['orderRegion', 'psdRegion', 'pscfRegion'] as $field) {
                if (!isset($context[$field]) || !is_int($context[$field]) || $context[$field] < 1 || $context[$field] > 999999) {
                    throw new BusinessException('Tabela inválida: '.$field.'.');
                }
            }

            if ($context['psdRegion'] === $context['pscfRegion']) {
                throw new BusinessException('As referências PSD e PSCF devem ser distintas.');
            }
            $key = $context['branch'].':'.$context['orderRegion'];

            if (isset($seen[$key])) {
                throw new BusinessException('Pareamento duplicado para filial/tabela.');
            }
            $seen[$key] = true;
        }
        $contexts = array_map(static fn (array $c): array => array_intersect_key($c, array_flip(['branch', 'orderRegion', 'psdRegion', 'pscfRegion'])), $contexts);
        $atgPercentage = Money::decimal(Input::text($data, 'atgPercentage', 12), 4);
        $returnPercentage = Money::decimal(Input::text($data, 'returnPercentage', 12), 4);
        $atgReturnPercentage = Money::decimal(Input::text($data, 'atgReturnPercentage', 12), 4);
        foreach ([$atgPercentage, $returnPercentage, $atgReturnPercentage] as $value) {
            if (!BigDecimal::of($value)->isPositive() || BigDecimal::of($value)->isGreaterThan(100)) {
                throw new BusinessException('Percentual de devolução deve ser maior que zero e no máximo 100.');
            }
        }
        foreach (['subtractFreight', 'applyReferenceDiscount'] as $key) {
            if (!isset($data[$key]) || !is_bool($data[$key])) {
                throw new BusinessException('Opção de cálculo inválida: '.$key.'.');
            }
        }
        $reason = Input::text($data, 'reason', 2000, 10);
        $settings = ['percentage' => $percentage, 'basis' => $basis, 'priceContexts' => $contexts, 'atgPercentage' => $atgPercentage, 'returnPercentage' => $returnPercentage, 'atgReturnPercentage' => $atgReturnPercentage, 'subtractFreight' => $data['subtractFreight'], 'applyReferenceDiscount' => $data['applyReferenceDiscount']];

        return $this->em->wrapInTransaction(function () use ($settings, $reason, $actor, $data) {
            $this->em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(749080)');
            $current = $this->current();

            if (($data['expectedVersion'] ?? null) !== $current['version']) {
                throw new BusinessException('A regra foi alterada por outro administrador. Recarregue antes de salvar.', 409);
            }
            $rule = new CommissionRule($settings, $reason, $actor);
            $this->em->persist($rule);
            $this->em->flush();
            $this->audit->record($actor, 'commission_rule.changed', 'commission-rule:'.$rule->view()['version'], ['previous' => $current, 'current' => $rule->view()]);

            return $rule->view();
        });
    }
}
