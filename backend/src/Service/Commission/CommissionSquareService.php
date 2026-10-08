<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Commission\Output\CommissionSquareOutput;
use App\Exception\Business\BusinessException;

final class CommissionSquareService
{
    private const array PAIRS = [
        ['name' => 'CE', 'psdSquare' => 573, 'pscfSquare' => 562, 'psdRegion' => 1, 'pscfRegion' => 2],
        ['name' => 'MA', 'psdSquare' => 539, 'pscfSquare' => 563, 'psdRegion' => 5, 'pscfRegion' => 6],
        ['name' => 'PI', 'psdSquare' => 570, 'pscfSquare' => 572, 'psdRegion' => 7, 'pscfRegion' => 8],
        ['name' => 'BA', 'psdSquare' => 1097, 'pscfSquare' => 1100, 'psdRegion' => 30, 'pscfRegion' => 32],
        ['name' => 'PE', 'psdSquare' => 1098, 'pscfSquare' => 1101, 'psdRegion' => 31, 'pscfRegion' => 33],
        ['name' => 'Parnaíba', 'psdSquare' => 1103, 'pscfSquare' => 1104, 'psdRegion' => 7, 'pscfRegion' => 8],
    ];

    /** @return list<CommissionSquareOutput> */
    public function list(): array
    {
        $items = [];
        foreach (self::PAIRS as $pair) {
            foreach (['pscf', 'psd'] as $type) {
                $items[] = new CommissionSquareOutput($pair[$type.'Square'], $pair['name'], $type, $pair['psdSquare'], $pair['pscfSquare'], $pair['psdRegion'], $pair['pscfRegion']);
            }
        }

        return $items;
    }

    public function get(int $code): CommissionSquareOutput
    {
        foreach ($this->list() as $square) {
            if ($code === $square->code) {
                return $square;
            }
        }

        throw new BusinessException('Praça sem correlação PSD/PSCF cadastrada.', 409);
    }

    public function validate(int $code, string $mode): CommissionSquareOutput
    {
        $square = $this->get($code);

        if ('normal' === $mode && 'pscf' !== $square->type) {
            throw new BusinessException('Comissão normal aceita somente pedidos de praças PSCF. Pedidos PSD × PSD exigem ATG.', 409);
        }

        if (null === $square->psdRegion || null === $square->pscfRegion) {
            throw new BusinessException('Confirme a correlação de tabelas PSD/PSCF desta praça antes de lançar comissões.', 409);
        }

        return $square;
    }
}
