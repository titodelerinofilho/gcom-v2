<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\BusinessException;
use App\Integration\Winthor\MovementGatewayInterface;
use App\Integration\Winthor\OrderGatewayInterface;
use App\Service\ApiView;
use App\Service\Input;
use App\Service\ReturnImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/winthor')]
final class WinthorController extends AbstractController
{
    #[Route('/{kind}', requirements: ['kind' => 'orders|returns|cancellations|overdue'], methods: ['GET'])]
    public function lookup(string $kind, Request $request, OrderGatewayInterface $orders, MovementGatewayInterface $movements): JsonResponse
    {
        $data = $request->query->all();
        $customer = $this->number($data, 'customer');

        if ('returns' === $kind) {
            return $this->json(['items' => $movements->returns($customer, $request->query->getBoolean('atg'))]);
        }

        if ('overdue' === $kind) {
            return $this->json(['items' => $movements->overdue($customer)]);
        }
        $from = Input::date($data, 'from');
        $to = Input::date($data, 'to');

        if ($from > $to || $from->diff($to)->days > 366) {
            throw new BusinessException('Informe um período válido de até 366 dias.');
        }
        $square = isset($data['square']) && '' !== $data['square'] ? (int) $this->number($data, 'square') : null;
        $rows = 'orders' === $kind
            ? $orders->search($customer, $from->format('Y-m-d'), $to->format('Y-m-d'), $square)
            : $movements->cancellations($customer, $from->format('Y-m-d'), $to->format('Y-m-d'));

        return $this->json(['items' => $rows]);
    }

    #[Route('/returns/import', methods: ['POST']), IsGranted('ROLE_OPERATOR')]
    public function importReturn(Request $request, ReturnImporter $importer): JsonResponse
    {
        $data = $request->toArray();

        if (!isset($data['atg']) || !is_bool($data['atg'])) {
            throw new BusinessException('Informe se a devolução é ATG.');
        }

        return $this->json(ApiView::adjustment($importer->import($this->number($data, 'customer'), $this->number($data, 'numtransent'), $data['atg'], $this->getUser())), 201);
    }

    private function number(array $data, string $key): string
    {
        $value = Input::text($data, $key, 18);

        if (!preg_match('/^[1-9][0-9]{0,17}$/D', $value)) {
            throw new BusinessException('Identificador inválido: '.$key.'.');
        }

        return $value;
    }
}
